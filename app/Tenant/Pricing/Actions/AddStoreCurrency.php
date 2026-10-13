<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Features\Features;
use App\Shared\Money\Decimal;
use App\Tenant\Pricing\Exceptions\CurrencyIsBaseException;
use App\Tenant\Pricing\Exceptions\StoreCurrencyExistsException;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a currency the store sells in besides its base currency, within its plan's presentment_currencies limit (section 3.3). Audited.
 *
 * Only active currencies count towards the limit; the base currency never
 * does. Customers see it once it has a rate: the market rate (fetched
 * hourly), or a manual one.
 */
final readonly class AddStoreCurrency
{
    public const string LIMIT_KEY = 'presentment_currencies';

    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private Features $features,
    ) {}

    /**
     * @param  string  $currencyCode  ISO 4217, already validated.
     * @param  Decimal  $marginPercent  From 0 to 25, already validated.
     *
     * @throws CurrencyIsBaseException When it is the store's base currency.
     * @throws StoreCurrencyExistsException When the store already has it, even inactive.
     * @throws UsageLimitReachedException When the plan allows no more active currencies.
     */
    public function handle(string $currencyCode, Decimal $marginPercent): StoreCurrency
    {
        if ($currencyCode === $this->findStoreSettings->handle()->currency_code) {
            throw new CurrencyIsBaseException;
        }

        if (StoreCurrency::query()->where('currency_code', $currencyCode)->exists()) {
            throw new StoreCurrencyExistsException;
        }

        $this->features->ensureWithinLimit(self::LIMIT_KEY, StoreCurrency::query()->active()->count());

        $currency = new StoreCurrency;
        $currency->currency_code = $currencyCode;
        $currency->margin_percent = $marginPercent;

        try {
            $currency->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new StoreCurrencyExistsException(previous: $exception);
        }

        return $currency;
    }
}
