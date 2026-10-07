<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Shared\Geography\Geography;
use App\Shared\Money\TaxMode;
use App\Shared\Tenancy\Contracts\StoreProfile;
use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Settings\Enums\DimensionUnit;
use App\Tenant\Settings\Enums\WeightUnit;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;

/**
 * A new store's first settings: what it was registered with (name, country, currency, timezone, language) plus its country's tax mode and display units.
 *
 * Country defaults apply only here, at sign-up (section 9.1); changing the
 * country later changes nothing else. The row is inserted only if missing,
 * so setting a store up again never overwrites settings the merchant has
 * changed. Only setting a store up calls this: reads never create settings
 * (section 3.3).
 */
final readonly class StoreSettingsDefaults implements StoreSettingsSetup
{
    public function __construct(
        private StoreProfile $storeProfile,
        private Geography $geography,
    ) {}

    public function initialize(): void
    {
        if (StoreSettings::query()->exists()) {
            return;
        }

        $profile = $this->storeProfile->current();
        $locale = $this->geography->isContentLocale($profile->locale) ? $profile->locale : config()->string('geography.default_locale');
        $usesImperialUnits = in_array($profile->countryCode, config()->array('geography.imperial_countries'), true);
        $now = CarbonImmutable::now();

        StoreSettings::query()->insertOrIgnore([
            'id' => 1,
            'name' => $profile->name,
            'country_code' => $profile->countryCode,
            'currency_code' => $profile->currencyCode,
            'timezone' => $profile->timezone,
            'default_locale' => $locale,
            'enabled_locales' => json_encode([$locale], JSON_THROW_ON_ERROR),
            'tax_mode' => ($this->geography->defaultsFor($profile->countryCode)->taxMode ?? TaxMode::Inclusive)->value,
            'weight_unit' => ($usesImperialUnits ? WeightUnit::Pound : WeightUnit::Kilogram)->value,
            'dimension_unit' => ($usesImperialUnits ? DimensionUnit::Inch : DimensionUnit::Centimetre)->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
