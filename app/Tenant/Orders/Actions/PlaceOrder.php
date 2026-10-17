<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Actions\TransferReservation;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Orders\Data\OrderDraft;
use App\Tenant\Orders\Data\PlacedOrder;
use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Events\OrderPlaced;
use App\Tenant\Orders\Exceptions\InvalidOrderDraftException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAddress;
use App\Tenant\Orders\Models\OrderDiscount;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\OrderShipping;
use App\Tenant\Orders\Models\OrderTax;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Pricing\Data\AppliedTax;
use App\Tenant\Pricing\Data\PricedLine;
use App\Tenant\Promotions\Actions\TransferPromotionUses;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Shipping\Data\ShippingOption;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a checkout into an order (section 13), for Checkout to call (Step 16): everything is copied as it was charged, so nothing that changes later (products, promotions, tax rates, exchange rates, saved addresses) changes the order.
 *
 * In one transaction: the order gets the store's next number and copies of
 * its lines (names in the buyer's language, SKU, price, discount, tax), each
 * discount per promotion, each tax applied, its shipping line and addresses;
 * the checkout's stock hold and promotion uses become the order's; and the
 * order gets a payment deadline, always: the usual one, or a later one the
 * draft gives, never beyond the maximum. A guest order whose email belongs to
 * a verified account joins that account. Placing the same checkout again
 * returns the same order. OrderPlaced is announced once it is saved.
 */
final readonly class PlaceOrder
{
    public function __construct(
        private TransferReservation $transferReservation,
        private TransferPromotionUses $transferPromotionUses,
        private OrderEventLog $orderEventLog,
    ) {}

    /**
     * @throws InvalidOrderDraftException When the draft can't become an order as it stands.
     */
    public function handle(OrderDraft $draft): PlacedOrder
    {
        $this->ensureComplete($draft);
        $paymentDueAt = $this->paymentDeadline($draft);

        return DB::transaction(function () use ($draft, $paymentDueAt): PlacedOrder {
            // Two requests placing the same checkout at once: the second waits, then finds the first's order.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['order-checkout:'.$draft->checkoutId]);

            $existing = Order::query()->where('checkout_id', $draft->checkoutId)->first();

            if ($existing !== null) {
                return new PlacedOrder($existing, false);
            }

            $linkedCustomer = $draft->buyer->customer ?? $this->verifiedAccountWith($draft->buyer->email);
            $order = $this->createOrder($draft, $linkedCustomer, $paymentDueAt);
            $this->copyLines($order, $draft);
            $this->copyShipping($order, $draft);
            $this->copyAddresses($order, $draft);
            $this->orderEventLog->record($order, OrderEventType::Placed, ['number' => $order->number]);

            if ($draft->buyer->customer === null && $linkedCustomer !== null) {
                $this->orderEventLog->record($order, OrderEventType::LinkedToCustomer, ['customer' => $linkedCustomer->public_id, 'when' => 'placed']);
            }

            $this->transferReservation->handle($draft->stockHolder, StockHolder::of($order));
            $this->transferPromotionUses->handle($draft->promotionHolder, new RedemptionHolder($order->getMorphClass(), $order->public_id));

            OrderPlaced::dispatch($order);

            return new PlacedOrder($order, true);
        });
    }

    /**
     * Refuses a draft whose parts don't fit together: Checkout must hand over exactly what it charged.
     *
     * @throws InvalidOrderDraftException
     */
    private function ensureComplete(OrderDraft $draft): void
    {
        $priced = $draft->basket->priced;
        $outcome = $draft->basket->discounts;

        if ($priced->lines === []) {
            throw new InvalidOrderDraftException('An order needs at least one line.');
        }

        if ($outcome->currency !== $priced->currency) {
            throw new InvalidOrderDraftException('The discounts and the prices must be in the same currency.');
        }

        $ships = ProductVariant::withTrashed()->whereIn('id', array_map(static fn (PricedLine $line): int => $line->variantId, array_values($priced->lines)))->where('requires_shipping', true)->exists();

        if ($ships !== ($draft->shipping !== null)) {
            throw new InvalidOrderDraftException($ships ? 'Lines that ship need a shipping method.' : 'Nothing ships, so there is no shipping method.');
        }

        if ($draft->shipping !== null) {
            $this->ensureShippingMatches($draft, $draft->shipping);
        }

        foreach ($priced->lines as $key => $line) {
            $fromDiscounts = Money::sum($priced->currency, array_map(static fn (DiscountProposal $discount): Money => $discount->lineDiscounts[$key] ?? Money::zero($priced->currency), $outcome->applied));

            if (! $fromDiscounts->isEqualTo($line->discount)) {
                throw new InvalidOrderDraftException("Line \"{$key}\": its discount must be what its promotions took off.");
            }
        }
    }

    /**
     * @throws InvalidOrderDraftException
     */
    private function ensureShippingMatches(OrderDraft $draft, ShippingOption $shipping): void
    {
        $priced = $draft->basket->priced;

        if ($shipping->amount === null) {
            throw new InvalidOrderDraftException('A carrier\'s rate must be quoted before the order is placed.');
        }

        if ($shipping->method->fulfilment->needsAddress() && $draft->shippingAddress === null) {
            throw new InvalidOrderDraftException('Shipping to the buyer needs their address.');
        }

        $charged = $shipping->amount->subtract($draft->basket->discounts->shippingDiscount);
        $taxable = $priced->shipping === null ? Money::zero($priced->currency) : ($priced->taxMode === TaxMode::Inclusive ? $priced->shipping->gross : $priced->shipping->net);

        if (! $charged->isEqualTo($taxable)) {
            throw new InvalidOrderDraftException('The shipping charged must be the chosen method\'s price after its discounts.');
        }
    }

    /**
     * The usual deadline, or the draft's later one, never beyond the maximum.
     *
     * @throws InvalidOrderDraftException
     */
    private function paymentDeadline(OrderDraft $draft): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        if ($draft->paymentDueAt === null) {
            return $now->addMinutes(max(1, config()->integer('orders.unpaid_expiry_minutes')));
        }

        if (! $draft->paymentDueAt->isAfter($now)) {
            throw new InvalidOrderDraftException('A payment deadline must be in the future.');
        }

        if ($draft->paymentDueAt->isAfter($now->addMinutes(config()->integer('orders.max_payment_window_minutes')))) {
            throw new InvalidOrderDraftException('A payment deadline can\'t be later than orders.max_payment_window_minutes from now.');
        }

        return $draft->paymentDueAt;
    }

    /**
     * The account a guest order joins: one whose verified email is the order's.
     */
    private function verifiedAccountWith(string $email): ?Customer
    {
        return Customer::query()->whereRaw('lower(email) = ?', [$email])->whereNotNull('email_verified_at')->first();
    }

    private function createOrder(OrderDraft $draft, ?Customer $customer, CarbonImmutable $paymentDueAt): Order
    {
        $priced = $draft->basket->priced;
        $currency = $priced->currency;
        $rate = $priced->exchangeRate;
        $shippingPrice = $draft->shipping->amount ?? Money::zero($currency);

        $order = new Order;
        $order->forceFill([
            'checkout_id' => $draft->checkoutId,
            'status' => OrderStatus::Open,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'is_test' => $draft->isTest,
            'fulfilment_status' => $draft->shipping === null ? FulfilmentStatus::NotRequired : FulfilmentStatus::Unfulfilled,
            'customer_id' => $customer?->id,
            'contact_email' => $draft->buyer->email,
            'contact_name' => $draft->buyer->name,
            'contact_phone' => $draft->buyer->phone,
            'locale' => $draft->locale,
            'currency' => $currency,
            'base_currency' => $rate->baseCurrency,
            'exchange_rate' => $rate->rate,
            'market_rate' => $rate->marketRate,
            'margin_percent' => $rate->marginPercent,
            'rate_source' => $rate->source,
            'rate_as_of' => $rate->asOf,
            'tax_mode' => $priced->taxMode,
            'tax_calculated_by' => $priced->taxCalculatedBy,
            'tax_country_code' => $priced->destination->countryCode,
            'tax_state_code' => $priced->destination->stateCode,
            'tax_postal_code' => $priced->destination->postalCode,
            'subtotal' => $priced->subtotal(),
            'discount' => $priced->discount(),
            'shipping' => $shippingPrice,
            'shipping_discount' => $draft->shipping === null ? Money::zero($currency) : $draft->basket->discounts->shippingDiscount,
            'tax' => $priced->tax(),
            'total' => $priced->total(),
            'base_total' => self::inBaseCurrency($priced->total(), $rate->baseCurrency, $rate->rate),
            'customer_note' => $draft->customerNote,
            'placed_at' => CarbonImmutable::now(),
            'payment_due_at' => $paymentDueAt,
        ])->save();

        return $order->refresh();
    }

    /**
     * An amount in the order's currency back in the base currency at the order's rate (base × rate = amount), rounded half-up.
     */
    private static function inBaseCurrency(Money $amount, string $baseCurrency, Decimal $rate): Money
    {
        if ($amount->currency() === $baseCurrency) {
            return $amount;
        }

        $major = Decimal::of($amount->minorAmount())->divideBy(Decimal::of(10 ** $amount->decimalPlaces())->multiplyBy($rate), 12);

        return Money::fromDecimal($major, $baseCurrency);
    }

    private function copyLines(Order $order, OrderDraft $draft): void
    {
        $priced = $draft->basket->priced;
        $outcome = $draft->basket->discounts;
        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::withTrashed()->with(['product', 'attributeValues'])->whereIn('id', array_map(static fn (PricedLine $line): int => $line->variantId, array_values($priced->lines)))->get()->keyBy('id');
        $promotions = Promotion::withTrashed()->whereIn('public_id', array_map(static fn (DiscountProposal $discount): string => $discount->reference, array_values(array_filter($outcome->applied, static fn (DiscountProposal $discount): bool => $discount->source === 'promotion'))))->pluck('id', 'public_id');
        $position = 0;

        foreach ($priced->lines as $key => $pricedLine) {
            $variant = $variants->get($pricedLine->variantId) ?? throw new InvalidOrderDraftException("Line \"{$key}\": its variant doesn't exist.");
            $line = $this->copyLine($order, $position++, $pricedLine, $variant, $draft->locale);

            foreach ($outcome->applied as $discount) {
                $amount = $discount->lineDiscounts[$key] ?? null;

                if ($amount !== null && $amount->isPositive()) {
                    $this->copyDiscount($order, $line, $discount, $amount, $promotions->get($discount->reference), $outcome->code);
                }
            }

            $this->copyTaxes($order, $line, $pricedLine->tax->taxes);
        }

        foreach ($outcome->applied as $discount) {
            if ($discount->shippingDiscount !== null && $discount->shippingDiscount->isPositive()) {
                $this->copyDiscount($order, null, $discount, $discount->shippingDiscount, $promotions->get($discount->reference), $outcome->code);
            }
        }
    }

    private function copyLine(Order $order, int $position, PricedLine $pricedLine, ProductVariant $variant, string $locale): OrderLine
    {
        $options = $variant->attributeValues->map(static fn (AttributeValue $value): string => $value->getTranslation('label', $locale))->filter()->implode(' / ');

        $line = new OrderLine;
        $line->forceFill([
            'order_id' => $order->id,
            'position' => $position,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => mb_substr($variant->product->getTranslation('name', $locale), 0, 255),
            'variant_name' => $options === '' ? null : mb_substr($options, 0, 255),
            'sku' => $variant->sku,
            'requires_shipping' => $variant->requires_shipping,
            'weight_grams' => $variant->weight_grams,
            'tax_class_id' => $pricedLine->taxClass->id,
            'tax_class_name' => $pricedLine->taxClass->name,
            'tax_outcome' => $pricedLine->tax->outcome,
            'quantity' => $pricedLine->quantity,
            'unit_price' => $pricedLine->price->unitPrice,
            'price_is_fixed' => $pricedLine->price->isFixed,
            'subtotal' => $pricedLine->subtotal,
            'discount' => $pricedLine->discount,
            'net' => $pricedLine->tax->net,
            'tax' => $pricedLine->tax->tax,
            'total' => $pricedLine->total(),
        ])->save();

        return $line;
    }

    private function copyDiscount(Order $order, ?OrderLine $line, DiscountProposal $discount, Money $amount, ?int $promotionId, ?string $code): void
    {
        (new OrderDiscount)->forceFill([
            'order_id' => $order->id,
            'order_line_id' => $line?->id,
            'source' => $discount->source,
            'reference' => $discount->reference,
            'promotion_id' => $discount->source === 'promotion' ? $promotionId : null,
            'code' => $discount->isCode ? $code : null,
            'label' => mb_substr($discount->label, 0, 255),
            'class' => $discount->class,
            'discount' => $amount,
        ])->save();
    }

    /**
     * @param  list<AppliedTax>  $taxes
     */
    private function copyTaxes(Order $order, ?OrderLine $line, array $taxes): void
    {
        foreach ($taxes as $tax) {
            (new OrderTax)->forceFill([
                'order_id' => $order->id,
                'order_line_id' => $line?->id,
                'name' => mb_substr($tax->name, 0, 120),
                'country_code' => $tax->countryCode,
                'state_code' => $tax->stateCode,
                'rate_percent' => $tax->ratePercent,
                'tax' => $tax->amount,
            ])->save();
        }
    }

    private function copyShipping(Order $order, OrderDraft $draft): void
    {
        $option = $draft->shipping;

        if ($option === null || $option->amount === null) {
            return;
        }

        $method = $option->method;
        $discount = $draft->basket->discounts->shippingDiscount;
        $tax = $draft->basket->priced->shipping;
        $net = $tax->net ?? $option->amount->subtract($discount);

        (new OrderShipping)->forceFill([
            'order_id' => $order->id,
            'shipping_method_id' => $method->id,
            'method_name' => mb_substr($method->getTranslation('name', $draft->locale), 0, 255),
            'fulfilment' => $method->fulfilment,
            'carrier' => $method->carrier,
            'carrier_service' => $method->carrier_service,
            'pickup_country_code' => $method->pickup_country_code,
            'pickup_state_code' => $method->pickup_state_code,
            'pickup_address_line1' => $method->pickup_address_line1,
            'pickup_city' => $method->pickup_city,
            'pickup_postal_code' => $method->pickup_postal_code,
            'price' => $option->amount,
            'discount' => $discount,
            'net' => $net,
            'tax' => $tax->tax ?? Money::zero($option->amount->currency()),
            'total' => $tax->gross ?? $net,
            'tax_outcome' => $tax?->outcome,
            'min_days' => $option->estimate?->minDays,
            'max_days' => $option->estimate?->maxDays,
            'earliest_on' => $option->estimate?->earliest->toDateString(),
            'latest_on' => $option->estimate?->latest->toDateString(),
        ])->save();

        if ($tax !== null) {
            $this->copyTaxes($order, null, $tax->taxes);
        }
    }

    private function copyAddresses(Order $order, OrderDraft $draft): void
    {
        foreach ([OrderAddressKind::Billing->value => $draft->billingAddress, OrderAddressKind::Shipping->value => $draft->shippingAddress] as $kind => $address) {
            if ($address instanceof AddressSnapshot) {
                (new OrderAddress)->forceFill(['order_id' => $order->id, 'kind' => $kind, 'version' => 1, ...$address->toArray()])->save();
            }
        }
    }
}
