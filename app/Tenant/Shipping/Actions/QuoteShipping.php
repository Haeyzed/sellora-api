<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Shared\Money\Money;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Shipping\Data\Parcel;
use App\Tenant\Shipping\Data\ShippableLine;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Data\ShippingQuote;
use App\Tenant\Shipping\Data\ShippingQuoteRequest;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Enums\ShippingPricing;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingMethodAmount;
use App\Tenant\Shipping\Models\ShippingRate;
use App\Tenant\Shipping\Models\ShippingRateAmount;
use App\Tenant\Shipping\Services\DeliveryEstimates;
use App\Tenant\Shipping\Services\ShippingZones;
use Illuminate\Database\Eloquent\Builder;

/**
 * What it costs to ship a basket to a destination, by each method offered there: the zone's methods, then pickups in the destination's country.
 *
 * Only lines that ship count: their weight in grams, and their value after
 * discounts, as displayed. A method's bracket for that weight or value gives
 * its price; with no bracket there, it isn't offered. Prices are the exact
 * amount set for the currency, or the base amount converted at the basket's
 * rate, rounded half-up to the currency's decimals. Value brackets and
 * "free over" thresholds are compared in the basket's currency, converted
 * the same way unless an exact threshold is set. A carrier method is quoted
 * live by its carrier once the full address is known, and is otherwise
 * offered as "calculated at checkout". A basket with nothing that ships needs
 * no shipping at all. Nothing is saved.
 */
final readonly class QuoteShipping
{
    public function __construct(
        private ShippingZones $zones,
        private QuoteCarrierRates $quoteCarrierRates,
        private DeliveryEstimates $deliveryEstimates,
    ) {}

    public function handle(ShippingQuoteRequest $request): ShippingQuote
    {
        $currency = $request->currency();
        $shipping = array_values(array_filter($request->lines, static fn (ShippableLine $line): bool => $line->variant->requires_shipping));
        $weight = array_sum(array_map(static fn (ShippableLine $line): int => ($line->variant->weight_grams ?? 0) * $line->quantity, $shipping));
        $value = Money::sum($currency, array_map(static fn (ShippableLine $line): Money => $line->value, $shipping));

        if ($shipping === []) {
            return new ShippingQuote(false, [], null, 0, $value);
        }

        $zone = $this->zones->for($request->destination);
        $destination = $request->destination;

        $methods = ShippingMethod::query()
            ->with(['rates.currencyAmounts', 'currencyAmounts'])
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->where(static fn (Builder $query) => $query
                ->when($zone !== null, static fn (Builder $inZone) => $inZone->where('shipping_zone_id', $zone?->id))
                ->orWhere(static fn (Builder $pickup) => $pickup->where('fulfilment', ShippingFulfilment::Pickup)->where('pickup_country_code', $destination->countryCode)))
            ->orderByRaw('case when fulfilment = ? then 1 else 0 end', [ShippingFulfilment::Pickup->value])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $options = [];
        $live = [];

        foreach ($methods as $method) {
            if ($method->pricing !== ShippingPricing::Carrier) {
                $options[$method->id] = self::optionFor($method, $weight, $value, $request->exchangeRate);
            } elseif (self::reachesFreeOver($method, $value, $request->exchangeRate)) {
                $options[$method->id] = new ShippingOption($method, Money::zero($currency), false, true);
            } elseif ($request->address === null) {
                // A live rate needs the full address: calculated at checkout.
                $options[$method->id] = new ShippingOption($method, null, false);
            } else {
                $options[$method->id] = null;
                $live[] = $method;
            }
        }

        if ($live !== [] && $request->address !== null) {
            $quoted = $this->quoteCarrierRates->handle($live, new Parcel($weight), $request->address, $request->exchangeRate);

            foreach ($live as $method) {
                $options[$method->id] = $quoted[$method->id] ?? null;
            }
        }

        $estimates = $this->deliveryEstimates;
        $offered = array_map(static fn (ShippingOption $option): ShippingOption => $option->withEstimate($estimates->for($option->method)), array_values(array_filter($options)));

        return new ShippingQuote(true, $offered, $zone === null ? ShippingQuote::NO_ZONE : null, $weight, $value);
    }

    /**
     * The method's price for the basket, or null when none of its brackets covers it.
     */
    private static function optionFor(ShippingMethod $method, int $weight, Money $value, AppliedExchangeRate $exchangeRate): ?ShippingOption
    {
        $rate = $method->rates->first(static fn (ShippingRate $rate): bool => match ($method->pricing) {
            ShippingPricing::Flat => true,
            ShippingPricing::Weight => $rate->covers($weight),
            ShippingPricing::OrderValue => self::valueIn($rate, $method, $value, $exchangeRate),
            ShippingPricing::Carrier => false,
        });

        if ($rate === null) {
            return null;
        }

        $exact = $rate->currencyAmounts->first(static fn (ShippingRateAmount $amount): bool => $amount->currency_code === $value->currency())?->price;
        $price = $exact ?? self::converted($rate->amount, $method, $exchangeRate);
        $isFree = self::reachesFreeOver($method, $value, $exchangeRate);

        return new ShippingOption($method, $isFree ? Money::zero($value->currency()) : $price, $exact !== null, $isFree);
    }

    /**
     * Whether the basket's value falls in an order-value bracket, its bounds converted into the basket's currency.
     */
    private static function valueIn(ShippingRate $rate, ShippingMethod $method, Money $value, AppliedExchangeRate $exchangeRate): bool
    {
        $from = self::converted($rate->range_from, $method, $exchangeRate);
        $to = $rate->range_to === null ? null : self::converted($rate->range_to, $method, $exchangeRate);

        return ! $value->isLessThan($from) && ($to === null || $value->isLessThan($to));
    }

    private static function reachesFreeOver(ShippingMethod $method, Money $value, AppliedExchangeRate $exchangeRate): bool
    {
        if ($method->free_over === null) {
            return false;
        }

        $threshold = $method->currencyAmounts->first(static fn (ShippingMethodAmount $amount): bool => $amount->currency_code === $value->currency())->free_over
            ?? self::converted($method->free_over->minorAmount(), $method, $exchangeRate);

        return ! $value->isLessThan($threshold);
    }

    /**
     * A base amount of the method in the basket's currency, converted at its rate and rounded half-up.
     */
    private static function converted(int $baseMinor, ShippingMethod $method, AppliedExchangeRate $exchangeRate): Money
    {
        $base = Money::ofMinor($baseMinor, $method->currency);

        return $base->currency() === $exchangeRate->currency ? $base : $base->convertTo($exchangeRate->currency, $exchangeRate->rate);
    }
}
