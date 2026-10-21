<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
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
            /** The store's logo; null when it has none. */
            'logo' => $this->whenLoaded('media', static fn (): ?StorefrontImageResource => ($logo = $settings->getFirstMedia(StoreSettings::LOGO)) === null ? null : new StorefrontImageResource($logo)),
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
            /** The stock level at or below which a variant counts as running low, unless the variant sets its own. */
            'low_stock_threshold' => $settings->low_stock_threshold,
            /** The days the store works, by ISO number (1 Monday to 7 Sunday): delivery estimates count only these. */
            'working_days' => $settings->working_days,
            /** How many years settled orders keep their buyer's personal data; null for the store country's default. */
            'order_retention_years' => $settings->order_retention_years,
            /** The store country's default, used while order_retention_years is null. */
            'order_retention_default_years' => $settings->defaultOrderRetentionYears(),
            /** How many years invoices and credit notes keep the buyer's details, as the store country's tax law requires, from the end of the year each was issued; it can't be changed. */
            'tax_record_retention_years' => $settings->taxRecordRetentionYears(),
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
            /** The name invoices and credit notes are issued in; null for the store name. */
            'legal_name' => $settings->legal_name,
            /** What the tax registration number is called on documents; null for the store country's usual one. */
            'tax_registration_label' => $settings->tax_registration_label,
            /** The label documents use while tax_registration_label is null. */
            'tax_registration_default_label' => $settings->countryTaxRegistrationLabel(),
            'tax_registration_number' => $settings->tax_registration_number,
            'invoice_prefix' => $settings->invoice_prefix,
            'credit_note_prefix' => $settings->credit_note_prefix,
            /** "yearly" (INV-2026-000001) or "continuous" (INV-000001). */
            'invoice_numbering' => $settings->invoice_numbering,
            /**
             * Printed at the foot of invoices and credit notes, by language.
             *
             * @var array<string, string>
             */
            'invoice_footer' => $settings->getTranslations('invoice_footer'),
            /**
             * The social sign-in providers offered to customers, such as ["google", "apple"].
             *
             * @var list<string>
             */
            'social_sign_in_providers' => $settings->social_sign_in_providers,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
