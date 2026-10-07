<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Resources;

use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The store's settings.
 *
 * @property StoreSettings $resource
 */
final class StoreSettingsResource extends JsonResource
{
    public function __construct(StoreSettings $settings, private readonly bool $pricingLocked)
    {
        parent::__construct($settings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        return [
            'name' => $settings->name,
            /** ISO 3166-1 alpha-2, such as "NG". */
            'country' => $settings->country_code,
            /** The base currency, ISO 4217, such as "NGN". */
            'currency' => $settings->currency_code,
            /** IANA, such as "Africa/Lagos". */
            'timezone' => $settings->timezone,
            /** The language content falls back to. */
            'default_locale' => $settings->default_locale,
            /** Every language the store publishes in, including the default. */
            'enabled_locales' => $settings->enabled_locales,
            'tax_mode' => $settings->tax_mode,
            /** True once anything in the store is priced: the currency and tax mode can no longer change. */
            'pricing_locked' => $this->pricingLocked,
            /** Whether every staff member must use two-factor authentication. Only the owner can change it. */
            'require_staff_two_factor' => $settings->require_staff_two_factor,
            'weight_unit' => $settings->weight_unit,
            'dimension_unit' => $settings->dimension_unit,
            'contact_email' => $settings->contact_email,
            /** E.164, such as "+2348012345678". */
            'contact_phone' => $settings->contact_phone,
            /** Null when the store has no address. */
            'address' => $settings->address_country_code === null ? null : [
                'line1' => $settings->address_line1,
                'line2' => $settings->address_line2,
                'city' => $settings->address_city,
                /** The state, province or region code within the country, such as "LA". */
                'state' => $settings->address_state_code,
                'postal_code' => $settings->address_postal_code,
                /** ISO 3166-1 alpha-2. */
                'country' => $settings->address_country_code,
            ],
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
