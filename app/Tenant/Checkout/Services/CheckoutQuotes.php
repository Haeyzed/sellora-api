<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Services;

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Cart\Actions\PriceCart;
use App\Tenant\Cart\Data\CheckoutPricing;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Data\CheckoutContext;
use App\Tenant\Checkout\Data\CheckoutIssue;
use App\Tenant\Checkout\Data\CheckoutQuote;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Customers\Actions\RecordMarketingConsent;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Enums\BuyerTaxIdRequirement;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Models\ShippingMethod;
use Carbon\CarbonImmutable;

/**
 * Works out a checkout's quote: the cart priced through PriceCart (the one calculation the cart shows and checkout charges) with everything the checkout knows, the ways to pay, and what is still missing.
 *
 * A quote is shown with a version and kept for its lifetime (15 minutes),
 * with the exchange rate it was priced at. Asked again within it, the kept
 * rate is used: if everything charged comes to the same, the same version
 * stands. Anything else (prices, stock, promotions, shipping, tax, payment
 * methods, or the kept rate going stale) gives a new version at today's rate.
 */
final readonly class CheckoutQuotes
{
    public function __construct(
        private PriceCart $priceCart,
        private CheckoutExtensions $checkoutExtensions,
        private FindStoreSettings $findStoreSettings,
        private RecordMarketingConsent $recordMarketingConsent,
    ) {}

    /**
     * The checkout's current quote, saved on it: the same version while nothing charged changed and it hasn't expired, otherwise a new one.
     */
    public function refresh(Checkout $checkout): CheckoutQuote
    {
        $kept = $this->keptRate($checkout);

        if ($kept !== null) {
            $quote = $this->quote($checkout, $kept);

            if ($quote->hash === $checkout->quote_hash) {
                return $quote;
            }
        }

        $quote = $this->quote($checkout, null);
        $this->remember($checkout, $quote);

        return $quote;
    }

    /**
     * The quote as it stands now at the rate the last one keeps, without saving anything: what placing the order compares with what was shown.
     */
    public function current(Checkout $checkout): CheckoutQuote
    {
        return $this->quote($checkout, $this->keptRate($checkout));
    }

    /**
     * Whether the checkout's last quote can still be placed as shown: not expired, and the version the shopper confirmed.
     */
    public function stands(Checkout $checkout, int $version): bool
    {
        return $checkout->quote_hash !== null
            && $checkout->quote_version === $version
            && $checkout->quote_expires_at?->isFuture() === true;
    }

    /**
     * Saves a quote on the checkout as a new version, with today's expiry and the rate it was priced at.
     */
    public function remember(Checkout $checkout, CheckoutQuote $quote): void
    {
        $rate = $quote->priced->currency->rate;

        $checkout->forceFill([
            'quote_version' => $checkout->quote_version + 1,
            'quote_hash' => $quote->hash,
            'quote_expires_at' => CarbonImmutable::now()->addMinutes(config()->integer('checkout.quote_expire_minutes')),
            'cart_version' => $checkout->cart->version,
            'exchange_rate' => [
                'base_currency' => $rate->baseCurrency,
                'currency' => $rate->currency,
                'rate' => $rate->rate->toString(),
                'market_rate' => $rate->marketRate?->toString(),
                'margin_percent' => $rate->marginPercent->toString(),
                'source' => $rate->source->value,
                'as_of' => $rate->asOf?->toIso8601String(),
            ],
        ])->save();
    }

    private function quote(Checkout $checkout, ?AppliedExchangeRate $rate): CheckoutQuote
    {
        $checkout->loadMissing(['cart.customer', 'customer', 'shippingMethod']);
        $settings = $this->findStoreSettings->handle();
        $shippingAddress = $checkout->shipping_address === null ? null : AddressSnapshot::fromArray($checkout->shipping_address);
        $billingAddress = $checkout->billing_address === null ? $shippingAddress : AddressSnapshot::fromArray($checkout->billing_address);
        $method = $checkout->shippingMethod;
        $shipsToBuyer = $method !== null && $method->fulfilment->needsAddress();

        $priced = $this->priceCart->handle($checkout->cart, new CheckoutPricing(
            $shipsToBuyer ? $shippingAddress : null,
            $billingAddress?->toTaxAddress(),
            $method,
            $checkout->contact_email,
            $rate,
        ));

        $issues = $this->cartIssues($priced);
        $paymentMethods = [];
        $offeredBy = [];
        $chosen = null;
        $moduleCovers = [];

        if ($priced->basket !== null) {
            $context = new CheckoutContext($checkout, $checkout->customer, $priced->basket->priced->total(), $billingAddress?->countryCode, $checkout->contact_email);
            [$paymentMethods, $offeredBy] = $this->offers($context);
            [$chosen, $moduleCovers, $paymentIssues] = $this->chosenMethods($checkout, $paymentMethods, $context->total);
            $issues = [...$issues, ...$this->buyerIssues($checkout, $priced, $method, $shippingAddress, $billingAddress, $settings->buyer_tax_id_requirement), ...$paymentIssues];

            foreach ($this->checkoutExtensions->validators() as $validator) {
                $issues = [...$issues, ...$validator->validate($context)];
            }
        }

        return new CheckoutQuote(
            $checkout,
            $priced,
            $paymentMethods,
            $chosen,
            $moduleCovers,
            $issues,
            $settings->buyer_tax_id_requirement === BuyerTaxIdRequirement::Required,
            [
                'email' => $this->recordMarketingConsent->wording(MarketingChannel::Email, $checkout->locale)['text'],
                'sms' => $this->recordMarketingConsent->wording(MarketingChannel::Sms, $checkout->locale)['text'],
            ],
            $this->hash($priced, $chosen, $moduleCovers),
            $offeredBy,
        );
    }

    /**
     * The rate the checkout's last quote keeps, while it hasn't expired and the rate hasn't gone stale (a market rate older than the store's limit); null for today's.
     */
    private function keptRate(Checkout $checkout): ?AppliedExchangeRate
    {
        $kept = $checkout->exchange_rate;

        if ($kept === null || $checkout->quote_expires_at?->isFuture() !== true) {
            return null;
        }

        $source = ExchangeRateSource::from((string) $kept['source']);
        $asOf = $kept['as_of'] === null ? null : CarbonImmutable::parse($kept['as_of']);

        if ($source === ExchangeRateSource::Market && ($asOf === null || $asOf->lessThan(CarbonImmutable::now()->subHours(config()->integer('exchange_rates.stale_after_hours'))))) {
            return null;
        }

        return new AppliedExchangeRate(
            (string) $kept['base_currency'],
            (string) $kept['currency'],
            Decimal::of((string) $kept['rate']),
            $kept['market_rate'] === null ? null : Decimal::of($kept['market_rate']),
            Decimal::of((string) $kept['margin_percent']),
            $source,
            $asOf,
        );
    }

    /**
     * @return list<CheckoutIssue>
     */
    private function cartIssues(PricedCart $priced): array
    {
        $issues = array_map(static fn (string $blocker): CheckoutIssue => new CheckoutIssue($blocker, $blocker === PricedCart::CART_EMPTY || $blocker === PricedCart::LINES_UNAVAILABLE ? null : 'shipping_method'), $priced->checkoutBlockers());

        if ($priced->shipping->option !== null && $priced->shipping->option->amount === null) {
            // A carrier's rate is quoted live once the full address is known.
            $issues[] = new CheckoutIssue('shipping_rate_unavailable', 'shipping_address');
        }

        return $issues;
    }

    /**
     * @return list<CheckoutIssue>
     */
    private function buyerIssues(Checkout $checkout, PricedCart $priced, ?ShippingMethod $method, ?AddressSnapshot $shippingAddress, ?AddressSnapshot $billingAddress, BuyerTaxIdRequirement $taxIdRequirement): array
    {
        $issues = [];

        if ($checkout->contact_email === null) {
            $issues[] = new CheckoutIssue('contact_email_required', 'contact.email');
        }

        if ($method !== null && $method->fulfilment->needsAddress() && $shippingAddress === null) {
            $issues[] = new CheckoutIssue('shipping_address_required', 'shipping_address');
        }

        // Nothing is sent to the buyer, so tax is worked out to their billing address, which must be given.
        if (! $priced->shipping->quote->required && $billingAddress === null) {
            $issues[] = new CheckoutIssue('billing_country_required', 'billing_address');
        }

        if ($taxIdRequirement === BuyerTaxIdRequirement::Required && $checkout->buyer_tax_id === null) {
            $issues[] = new CheckoutIssue('buyer_tax_id_required', 'buyer_tax_id');
        }

        return $issues;
    }

    /**
     * Every offer, in the order the extensions were registered, and the extension behind each key (the first to offer it).
     *
     * @return array{list<OfferedPaymentMethod>, array<string, CheckoutPaymentMethod>}
     */
    private function offers(CheckoutContext $context): array
    {
        $offers = [];
        $offeredBy = [];

        foreach ($this->checkoutExtensions->paymentMethods() as $method) {
            foreach ($method->offers($context) as $offer) {
                if (isset($offeredBy[$offer->key])) {
                    continue;
                }

                $offers[] = $offer;
                $offeredBy[$offer->key] = $method;
            }
        }

        return [$offers, $offeredBy];
    }

    /**
     * Core's chosen method and what each chosen module method covers, with what is wrong with the choice.
     *
     * @param  list<OfferedPaymentMethod>  $offers
     * @return array{OfferedPaymentMethod|null, array<string, Money>, list<CheckoutIssue>}
     */
    private function chosenMethods(Checkout $checkout, array $offers, Money $total): array
    {
        $byKey = [];

        foreach ($offers as $offer) {
            $byKey[$offer->key] = $offer;
        }

        $issues = [];
        $covers = [];
        $due = $total;

        foreach ($checkout->module_payment_methods as $key) {
            $offer = $byKey[$key] ?? null;

            if ($offer === null || $offer->kind !== PaymentMethodKind::Module) {
                $issues[] = new CheckoutIssue('payment_method_not_offered', 'module_payment_methods');

                continue;
            }

            $covered = $offer->covers === null || $offer->covers->isGreaterThan($due) ? $due : $offer->covers;
            $covers[$key] = $covered;
            $due = $due->subtract($covered);
        }

        $chosen = $checkout->payment_method === null ? null : ($byKey[$checkout->payment_method] ?? null);

        if ($chosen !== null && ! $chosen->kind->paysWhatIsLeft()) {
            $chosen = null;
        }

        if ($checkout->payment_method !== null && $chosen === null) {
            $issues[] = new CheckoutIssue('payment_method_not_offered', 'payment_method');
        } elseif ($chosen === null && $due->isPositive()) {
            $issues[] = new CheckoutIssue('payment_method_required', 'payment_method');
        }

        return [$chosen, $covers, $issues];
    }

    /**
     * Everything that would be charged, so a quote that comes to the same keeps its version and one that doesn't is shown again.
     *
     * @param  array<string, Money>  $moduleCovers
     */
    private function hash(PricedCart $priced, ?OfferedPaymentMethod $chosen, array $moduleCovers): string
    {
        $rate = $priced->currency->rate;
        $basket = $priced->basket;
        $amount = static fn (?Money $money): ?int => $money?->minorAmount();

        return hash('sha256', (string) json_encode([
            'currency' => $rate->currency,
            'rate' => [$rate->rate->toString(), $rate->source->value, $rate->asOf?->toIso8601String()],
            'lines' => array_map(static fn (PricedCartLine $line): array => [
                $line->line->public_id, $line->line->product_variant_id, $line->line->quantity, $line->status->value,
                $amount($line->priced?->price->unitPrice), $amount($line->priced?->discount), $amount($line->priced?->tax->tax), $amount($line->priced?->total()),
            ], $priced->lines),
            'discounts' => $basket === null ? [] : array_map(static fn (DiscountProposal $discount): array => [$discount->source, $discount->reference, $amount($discount->total($rate->currency))], $basket->discounts->applied),
            'shipping' => [
                $priced->shipping->option?->method->public_id,
                $amount($priced->shipping->option?->amount),
                $amount($priced->shipping->discount),
                $amount($basket?->priced->shipping?->tax),
            ],
            'tax_destination' => $basket === null ? null : [$basket->priced->destination->countryCode, $basket->priced->destination->stateCode, $basket->priced->destination->postalCode],
            'totals' => $basket === null ? null : [
                $amount($basket->priced->subtotal()), $amount($basket->priced->discount()), $amount($basket->priced->tax()), $amount($basket->priced->total()),
            ],
            'payment' => [
                $chosen === null ? null : [$chosen->key, $chosen->isTest, $chosen->payOnDelivery],
                array_map($amount, $moduleCovers),
            ],
            'shipping_fulfilment' => $priced->shipping->option?->method->fulfilment === ShippingFulfilment::Pickup,
        ]));
    }
}
