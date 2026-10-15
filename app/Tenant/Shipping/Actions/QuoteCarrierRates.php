<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Actions;

use App\Shared\Money\Money;
use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Data\CarrierAddress;
use App\Tenant\Shipping\Data\CarrierQuoteRequest;
use App\Tenant\Shipping\Data\CarrierRate;
use App\Tenant\Shipping\Data\Parcel;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingMethodAmount;
use App\Tenant\Shipping\ShippingCarrierRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prices carrier methods with their carriers' live rates, once a full address is known (checkout, or a staff preview).
 *
 * Each carrier is asked once for all its methods, and must answer within
 * the configured timeout; a slower answer counts as none. A good answer is
 * reused for the same parcels, addresses and currency for a few minutes,
 * under a key that is a hash, so no address is ever readable in the cache;
 * failures are never kept. When a carrier fails, or isn't usable in the
 * store, each of its methods falls back to its backup price, or isn't
 * offered. A rate is used in the basket's currency as given, or converted
 * from the base currency; any other currency isn't trusted.
 */
final readonly class QuoteCarrierRates
{
    public const string CACHE_PREFIX = 'shipping-carrier-quote:';

    public function __construct(
        private ShippingCarrierRegistry $carriers,
        private FindStoreSettings $findStoreSettings,
    ) {}

    /**
     * @param  list<ShippingMethod>  $methods  Carrier-priced methods.
     * @return array<int, ShippingOption> By method ID; a method left out isn't offered.
     */
    public function handle(array $methods, Parcel $parcel, CarrierAddress $destination, AppliedExchangeRate $exchangeRate): array
    {
        $options = [];

        foreach (collect($methods)->groupBy('carrier') as $carrierKey => $carrierMethods) {
            $rates = $this->ratesOf((string) $carrierKey, new CarrierQuoteRequest($this->origin(), $destination, [$parcel], $exchangeRate->currency));

            foreach ($carrierMethods as $method) {
                $option = $rates === null ? self::backup($method, $exchangeRate) : self::live($method, $rates, $exchangeRate);

                if ($option !== null) {
                    $options[$method->id] = $option;
                }
            }
        }

        return $options;
    }

    /**
     * The carrier's rates, from the cache or from the carrier; null when it isn't usable, fails, or is too slow.
     *
     * @return list<CarrierRate>|null
     */
    private function ratesOf(string $carrierKey, CarrierQuoteRequest $request): ?array
    {
        $carrier = $this->carriers->enabled($carrierKey);

        if ($carrier === null) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX.hash('sha256', (string) json_encode([
            tenant()?->getTenantKey(),
            $carrierKey,
            $request->origin->parts(),
            $request->destination->parts(),
            array_map(static fn (Parcel $parcel): int => $parcel->weightGrams, $request->parcels),
            $request->currency,
        ]));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return array_values(array_map(static fn (array $rate): CarrierRate => new CarrierRate((string) $rate[0], Money::ofMinor((int) $rate[1], (string) $rate[2])), $cached));
        }

        $timeout = config()->integer('shipping.carrier_timeout_seconds');
        $started = hrtime(true);

        try {
            $rates = $carrier->quote($request, $timeout);
        } catch (Throwable $exception) {
            // Never the addresses: they are personal data.
            Log::warning('Shipping carrier could not quote.', ['carrier' => $carrierKey, 'error' => $exception::class]);

            return null;
        }

        if (hrtime(true) - $started > $timeout * 1_000_000_000) {
            Log::warning('Shipping carrier answered too late.', ['carrier' => $carrierKey]);

            return null;
        }

        Cache::put($cacheKey, array_map(static fn (CarrierRate $rate): array => [$rate->service, $rate->amount->minorAmount(), $rate->amount->currency()], $rates), now()->addMinutes(config()->integer('shipping.carrier_quote_cache_minutes')));

        return $rates;
    }

    /**
     * @param  list<CarrierRate>  $rates
     */
    private static function live(ShippingMethod $method, array $rates, AppliedExchangeRate $exchangeRate): ?ShippingOption
    {
        foreach ($rates as $rate) {
            if ($rate->service !== $method->carrier_service) {
                continue;
            }

            return match ($rate->amount->currency()) {
                $exchangeRate->currency => new ShippingOption($method, $rate->amount, true),
                $method->currency => new ShippingOption($method, $rate->amount->convertTo($exchangeRate->currency, $exchangeRate->rate), false),
                default => null,
            };
        }

        return null;
    }

    private static function backup(ShippingMethod $method, AppliedExchangeRate $exchangeRate): ?ShippingOption
    {
        if ($method->backup === null) {
            return null;
        }

        $exact = $method->currencyAmounts->first(static fn (ShippingMethodAmount $amount): bool => $amount->currency_code === $exchangeRate->currency && $amount->backup !== null)?->backup;

        if ($exact !== null) {
            return new ShippingOption($method, $exact, true);
        }

        return new ShippingOption($method, $method->backup->currency() === $exchangeRate->currency ? $method->backup : $method->backup->convertTo($exchangeRate->currency, $exchangeRate->rate), false);
    }

    private function origin(): CarrierAddress
    {
        $settings = $this->findStoreSettings->handle();

        return new CarrierAddress($settings->address_country_code ?? $settings->country_code, $settings->address_state_code, $settings->address_postal_code, $settings->address_city, $settings->address_line1);
    }
}
