<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Models;

use App\Shared\Media\Concerns\HasStorefrontImages;
use App\Shared\Media\StorefrontImage;
use App\Shared\Money\TaxMode;
use App\Shared\Tenancy\StoreProfileDetails;
use App\Tenant\Settings\Enums\DimensionUnit;
use App\Tenant\Settings\Enums\WeightUnit;
use App\Tenant\Settings\StoreLocales;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;

/**
 * The store's core settings: one row (a check constraint keeps it so), audited field by field.
 *
 * Holds core settings only; modules and integrations keep their own settings
 * in their own tables (section 10). Number formatting comes from ISO 4217 and
 * the language, never from here (section 3.3).
 *
 * @property int $id Always 1.
 * @property string $name
 * @property string $country_code ISO 3166-1 alpha-2.
 * @property string $currency_code The base currency, ISO 4217. Locked once anything is priced.
 * @property string $timezone IANA.
 * @property string $default_locale The language content falls back to.
 * @property list<string> $enabled_locales The languages the store publishes in, always including the default.
 * @property TaxMode $tax_mode Locked once anything is priced.
 * @property WeightUnit $weight_unit
 * @property DimensionUnit $dimension_unit
 * @property string|null $contact_email
 * @property string|null $contact_phone E.164, such as "+2348012345678".
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $address_city
 * @property string|null $address_state_code The state's code within the address country, such as "LA".
 * @property string|null $address_postal_code Free text; formats differ by country.
 * @property string|null $address_country_code ISO 3166-1 alpha-2.
 * @property int $low_stock_threshold The stock level at or below which a variant counts as running low, unless the variant sets its own.
 * @property bool $require_staff_two_factor Whether every staff member must use two-factor authentication; the owner's decision alone, never mass-assigned.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class StoreSettings extends Model implements AuditableContract, HasMedia
{
    /** The store's logo on its storefront; a new one replaces the old. */
    public const string LOGO = 'logo';

    use Auditable;
    use HasStorefrontImages;

    /** The settings that the platform keeps a copy of, so changing one is sent on to it (section 6). */
    public const array PROFILE_FIELDS = ['name', 'country_code', 'currency_code', 'timezone', 'default_locale'];

    /** The settings that decide how stored prices are read, locked once anything is priced (section 3.3). */
    public const array PRICING_FIELDS = ['currency_code', 'tax_mode'];

    public $incrementing = false;

    protected $table = 'store_settings';

    protected $fillable = [
        'name',
        'country_code',
        'currency_code',
        'timezone',
        'default_locale',
        'enabled_locales',
        'tax_mode',
        'weight_unit',
        'dimension_unit',
        'low_stock_threshold',
        'contact_email',
        'contact_phone',
        'address_line1',
        'address_line2',
        'address_city',
        'address_state_code',
        'address_postal_code',
        'address_country_code',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id'];

    /**
     * Saved settings may change the store's languages, so the remembered ones are forgotten.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LOGO)->singleFile()->useDisk(StorefrontImage::DISK);
    }

    protected static function booted(): void
    {
        self::saved(static function (): void {
            app(StoreLocales::class)->forget();
        });
    }

    /**
     * The details the platform keeps a copy of.
     */
    public function profileDetails(): StoreProfileDetails
    {
        return new StoreProfileDetails($this->name, $this->country_code, $this->currency_code, $this->timezone, $this->default_locale);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled_locales' => 'array',
            'require_staff_two_factor' => 'boolean',
            'low_stock_threshold' => 'integer',
            'tax_mode' => TaxMode::class,
            'weight_unit' => WeightUnit::class,
            'dimension_unit' => DimensionUnit::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
