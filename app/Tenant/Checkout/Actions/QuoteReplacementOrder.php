<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Data\ReplacementLine;
use App\Tenant\Checkout\Data\ReplacementQuote;
use App\Tenant\Checkout\Data\ReplacementRequest;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Inventory\Actions\FindVariantStockStatus;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Actions\PriceWithDiscounts;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\PriceAllowances;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Shipping\Actions\PriceBasketWithShipping;
use App\Tenant\Shipping\Data\CarrierAddress;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Data\ShippingRegion;
use LogicException;

/**
 * Works out what a replacement order in an exchange comes to now (Step 18c), through the same public steps checkout uses: whether each variant is for sale (Catalog) and can be bought (Inventory), and today's prices with today's automatic promotions, tax and shipping (PriceBasketWithShipping, never a code), then the exchange's allowances (PriceWithDiscounts).
 *
 * It is priced in the order's own currency at today's rate, sent to the
 * order's current shipping address (or pickup), for the order's buyer.
 * A free like-for-like line never costs more than what the returned units
 * cost the buyer, and a delivery the buyer doesn't pay for costs nothing:
 * both are allowances, taken off after the promotions and before tax. The
 * returned items then credit the smaller of what they are worth and what
 * the replacement costs. Reading changes nothing.
 */
final readonly class QuoteReplacementOrder
{
    public const string NOT_AVAILABLE = 'replacement_not_available';

    public const string OUT_OF_STOCK = 'replacement_out_of_stock';

    public const string CURRENCY_NOT_OFFERED = 'replacement_currency_not_offered';

    public const string SHIPPING_UNAVAILABLE = 'replacement_shipping_unavailable';

    /** How often a free line's allowance is nudged up by a minor unit when a tax rounding left it a unit over. */
    private const int MAX_ROUNDING_STEPS = 5;

    public function __construct(
        private FindExchangeRates $findExchangeRates,
        private FindVariantStockStatus $findVariantStockStatus,
        private PriceWithDiscounts $priceWithDiscounts,
        private PriceBasketWithShipping $priceBasketWithShipping,
    ) {}

    public function handle(ReplacementRequest $request): ReplacementQuote
    {
        $currency = $request->original->currency;
        $rate = $this->findExchangeRates->handle([$currency])[$currency] ?? null;
        $issues = $this->availabilityIssues($request);

        if ($rate === null) {
            $issues[] = self::CURRENCY_NOT_OFFERED;
        }

        if ($issues !== [] || $rate === null) {
            return $this->withIssues($request, $issues);
        }

        $shippingAddress = $request->shippingAddress();
        $billingAddress = $request->billingAddress();
        $method = $request->shippingMethod;
        $taxDestination = $method?->pickupTaxAddress() ?? ($method !== null && $method->fulfilment->needsAddress() ? $shippingAddress?->toTaxAddress() : null) ?? $billingAddress?->toTaxAddress();
        $buyer = $request->discountBuyer();
        $holder = $request->redemptionHolder();

        $destination = $this->shippingDestination($request, $shippingAddress, $billingAddress);

        if ($destination === null) {
            return $this->withIssues($request, [self::SHIPPING_UNAVAILABLE]);
        }

        // Priced with the delivery the way a cart and a checkout are, then the exchange's allowances on top.
        $shipped = $this->priceBasketWithShipping->handle(
            $rate,
            $this->pricingLines($request),
            $taxDestination,
            $destination,
            $shippingAddress === null ? null : new CarrierAddress($shippingAddress->countryCode, $shippingAddress->stateCode, $shippingAddress->postalCode, $shippingAddress->city, $shippingAddress->line1),
            $method,
            $buyer,
            holder: $holder,
        );
        $shipping = $shipped->option;

        if ($method !== null && $shipping?->amount === null) {
            return $this->withIssues($request, [self::SHIPPING_UNAVAILABLE]);
        }

        $basket = $this->withAllowances($request, $rate, $taxDestination, $shipped->basket ?? throw new LogicException('A replacement has lines.'), $shipping?->amount, $buyer, $holder);
        $total = $basket->priced->total();
        $credit = $request->returnedValue->isGreaterThan($total) ? $total : $request->returnedValue;

        return new ReplacementQuote(
            $basket,
            $shipping,
            $request->returnedValue,
            $total,
            $credit,
            $total->subtract($credit),
            $request->returnedValue->subtract($credit),
            [],
            $this->hash($request, $basket, $shipping, $credit),
        );
    }

    /**
     * Why lines can't be sent: a variant no longer for sale, or out of stock without backorders.
     *
     * @return list<string>
     */
    private function availabilityIssues(ReplacementRequest $request): array
    {
        $statuses = $this->findVariantStockStatus->handle(array_map(static fn (ReplacementLine $line): int => $line->variant->id, $request->lines));
        $issues = [];

        foreach ($request->lines as $line) {
            $issue = match (true) {
                ! $line->variant->isForSale() => self::NOT_AVAILABLE,
                ! $statuses[$line->variant->id]->canBeBought() => self::OUT_OF_STOCK,
                default => null,
            };

            if ($issue !== null && ! in_array($issue, $issues, true)) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * @param  list<string>  $issues
     */
    private function withIssues(ReplacementRequest $request, array $issues): ReplacementQuote
    {
        $zero = Money::zero($request->returnedValue->currency());

        return new ReplacementQuote(null, null, $request->returnedValue, $zero, $zero, $zero, $request->returnedValue, $issues, hash('sha256', (string) json_encode($issues)));
    }

    /**
     * Where the replacement would be sent, for shipping: the order's shipping address, a pickup's own region, or where the buyer is billed when nothing ships. Null when it must be sent to an address the order no longer has.
     */
    private function shippingDestination(ReplacementRequest $request, ?AddressSnapshot $shippingAddress, ?AddressSnapshot $billingAddress): ?ShippingRegion
    {
        $method = $request->shippingMethod;

        return match (true) {
            $shippingAddress !== null => new ShippingRegion($shippingAddress->countryCode, $shippingAddress->stateCode),
            $method !== null && $method->fulfilment->needsAddress() => null,
            $method?->pickup_country_code !== null => new ShippingRegion($method->pickup_country_code, $method->pickup_state_code),
            $billingAddress !== null => new ShippingRegion($billingAddress->countryCode, $billingAddress->stateCode),
            default => null,
        };
    }

    /**
     * The basket as priced with its delivery, then again with each free line's allowance (and a free delivery's), nudged by a minor unit while tax rounding leaves a free line over what it may cost.
     */
    private function withAllowances(ReplacementRequest $request, AppliedExchangeRate $rate, ?TaxAddress $destination, DiscountedBasket $basket, ?Money $shipping, DiscountBuyer $buyer, RedemptionHolder $holder): DiscountedBasket
    {
        $shippingAllowance = $request->chargesShipping ? null : $shipping;
        $lineAllowances = [];

        for ($step = 0; $step <= self::MAX_ROUNDING_STEPS; $step++) {
            $over = $this->overByLine($request, $basket);

            if ($over === [] && ($step > 0 || $shippingAllowance === null)) {
                return $basket;
            }

            foreach ($over as $key => $excess) {
                $lineAllowances[$key] = ($lineAllowances[$key] ?? Money::zero($rate->currency))->add($excess);
            }

            $basket = $this->price($request, $rate, $destination, $shipping, $buyer, $holder, new PriceAllowances($request->returnPublicId, $request->allowanceLabel, $lineAllowances, $shippingAllowance));
        }

        throw new LogicException('A free replacement line still costs more than the returned units after its allowance.');
    }

    /**
     * How much more each free line costs than it may, in what its allowance comes off: all of it where tax is included, and the share before tax where tax is added, rounded up.
     *
     * @return array<string, Money>
     */
    private function overByLine(ReplacementRequest $request, DiscountedBasket $basket): array
    {
        $over = [];

        foreach ($request->lines as $position => $line) {
            $priced = $basket->priced->lines[self::key($position)] ?? throw new LogicException('Every replacement line is priced.');

            if ($line->atMost === null || ! $priced->total()->isGreaterThan($line->atMost)) {
                continue;
            }

            $excess = $priced->total()->subtract($line->atMost)->minorAmount();
            $taxable = $priced->subtotal->subtract($priced->discount)->minorAmount();
            $gross = $priced->total()->minorAmount();
            $over[self::key($position)] = Money::ofMinor(max(1, intdiv($excess * $taxable + $gross - 1, $gross)), $line->atMost->currency());
        }

        return $over;
    }

    private function price(ReplacementRequest $request, AppliedExchangeRate $rate, ?TaxAddress $destination, ?Money $shipping, DiscountBuyer $buyer, RedemptionHolder $holder, ?PriceAllowances $allowances): DiscountedBasket
    {
        return $this->priceWithDiscounts->handle(
            $rate->currency,
            $this->pricingLines($request),
            $destination,
            $shipping,
            $buyer,
            null,
            holder: $holder,
            rate: $rate,
            allowances: $allowances,
        );
    }

    /**
     * @return list<array{key: string, variant: ProductVariant, quantity: int}>
     */
    private function pricingLines(ReplacementRequest $request): array
    {
        return array_map(static fn (ReplacementLine $line, int $position): array => ['key' => self::key($position), 'variant' => $line->variant, 'quantity' => $line->quantity], $request->lines, array_keys($request->lines));
    }

    private static function key(int $position): string
    {
        return 'replacement-'.($position + 1);
    }

    /**
     * Everything charged and settled, so placing can refuse a quote that came to anything else since it was shown.
     */
    private function hash(ReplacementRequest $request, DiscountedBasket $basket, ?ShippingOption $shipping, Money $credit): string
    {
        $priced = $basket->priced;
        $rate = $priced->exchangeRate;

        return hash('sha256', (string) json_encode([
            'currency' => $priced->currency,
            'rate' => [$rate->rate->toString(), $rate->source->value, $rate->asOf?->toIso8601String()],
            'lines' => array_map(static fn ($line): array => [$line->variantId, $line->quantity, $line->price->unitPrice->minorAmount(), $line->discount->minorAmount(), $line->tax->tax->minorAmount(), $line->total()->minorAmount()], $priced->lines),
            'discounts' => array_map(static fn (DiscountProposal $discount): array => [$discount->source, $discount->reference, $discount->total($priced->currency)->minorAmount()], $basket->discounts->applied),
            'shipping' => [$shipping?->method->public_id, $shipping?->amount?->minorAmount(), $basket->discounts->shippingDiscount->minorAmount(), $priced->shipping?->tax->minorAmount()],
            'tax_destination' => [$priced->destination->countryCode, $priced->destination->stateCode, $priced->destination->postalCode],
            'total' => $priced->total()->minorAmount(),
            'returned_value' => $request->returnedValue->minorAmount(),
            'credit' => $credit->minorAmount(),
        ]));
    }
}
