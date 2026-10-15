<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Enums\ShippingPricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * A way to get an order to the shopper, and what it costs: shipped or delivered by the store within a zone, or picked up at one of its locations.
 *
 * Its price comes from its rate brackets (flat, by weight or by order value),
 * in the base currency with optional exact amounts per offered currency, and
 * is nothing once the lines that ship are worth its "free over" threshold.
 * How it reaches the shopper and how it is priced are set once. Archived
 * rather than deleted, so orders keep what they chose. Audited, with changes
 * to its rates as one change.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $shipping_zone_id Null for a pickup, or an archived method whose zone was deleted.
 * @property array<string, string> $name What shoppers see, by language.
 * @property array<string, string>|null $description By language.
 * @property ShippingFulfilment $fulfilment
 * @property ShippingPricing $pricing
 * @property string|null $carrier A carrier-priced method's carrier, by its key (ShippingCarrierRegistry).
 * @property string|null $carrier_service The carrier's service code.
 * @property string $currency The base currency its amounts are in.
 * @property Money|null $free_over Free once the lines that ship are worth this much, after discounts, as displayed.
 * @property Money|null $backup A carrier-priced method's price when the carrier can't quote; null for not offered then.
 * @property int|null $stock_location_id A pickup's location.
 * @property string|null $pickup_country_code A pickup's address: offered to shoppers in this country.
 * @property string|null $pickup_state_code
 * @property string|null $pickup_address_line1
 * @property string|null $pickup_city
 * @property string|null $pickup_postal_code
 * @property array<string, string>|null $pickup_instructions By language, such as opening hours.
 * @property int|null $min_days The fewest business days it takes; null with max_days for no estimate.
 * @property int|null $max_days The most business days it takes.
 * @property int $position Lower first, among the methods offered together.
 * @property bool $is_active
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ShippingZone|null $zone
 * @property-read StockLocation|null $stockLocation
 * @property-read Collection<int, ShippingRate> $rates
 * @property-read Collection<int, ShippingMethodAmount> $currencyAmounts
 */
final class ShippingMethod extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;
    use HasStoreTranslations;

    /** The audit event recorded when a method's rates or exact amounts are replaced, with them before and after. */
    public const string RATES_REPLACED = 'rates_replaced';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description', 'pickup_instructions'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'fulfilment',
        'pricing',
        'carrier',
        'carrier_service',
        'currency',
        'free_over',
        'backup',
        'pickup_country_code',
        'pickup_state_code',
        'pickup_address_line1',
        'pickup_city',
        'pickup_postal_code',
        'min_days',
        'max_days',
        'position',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /**
     * @return HasMany<ShippingRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class)->orderBy('range_from');
    }

    /**
     * @return HasMany<ShippingMethodAmount, $this>
     */
    public function currencyAmounts(): HasMany
    {
        return $this->hasMany(ShippingMethodAmount::class)->orderBy('currency_code');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The weights or order values its brackets leave out, where it isn't offered: below the first, between two, and above the last when that one ends. A flat price leaves none.
     *
     * @return list<array{from: int, to: int|null}>
     */
    public function uncoveredRanges(): array
    {
        // A carrier prices it, not brackets.
        if ($this->pricing === ShippingPricing::Carrier) {
            return [];
        }

        $gaps = [];
        $covered = 0;

        foreach ($this->rates as $rate) {
            if ($rate->range_from > $covered) {
                $gaps[] = ['from' => $covered, 'to' => $rate->range_from];
            }

            $covered = $rate->range_to;

            if ($covered === null) {
                return $gaps;
            }
        }

        return [...$gaps, ['from' => $covered, 'to' => null]];
    }

    /**
     * Its rates and exact amounts as plain values, for audits.
     *
     * @return array{rates: list<array{from: int, to: int|null, amount: int, currency_amounts: array<string, int>}>, free_over_amounts: array<string, int|null>, backup_amounts: array<string, int|null>}
     */
    public function ratesSnapshot(): array
    {
        return [
            'rates' => array_values($this->rates->map(static fn (ShippingRate $rate): array => [
                'from' => $rate->range_from,
                'to' => $rate->range_to,
                'amount' => $rate->amount,
                'currency_amounts' => $rate->currencyAmounts->mapWithKeys(static fn (ShippingRateAmount $amount): array => [$amount->currency_code => $amount->price->minorAmount()])->all(),
            ])->all()),
            'free_over_amounts' => $this->currencyAmounts->mapWithKeys(static fn (ShippingMethodAmount $amount): array => [$amount->currency_code => $amount->free_over?->minorAmount()])->all(),
            'backup_amounts' => $this->currencyAmounts->mapWithKeys(static fn (ShippingMethodAmount $amount): array => [$amount->currency_code => $amount->backup?->minorAmount()])->all(),
        ];
    }

    /**
     * Audits a change to its rates or exact amounts as one change, with them before and after.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function auditRatesReplaced(array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        $this->auditEvent = self::RATES_REPLACED;
        $this->isCustomEvent = true;
        $this->auditCustomOld = $before;
        $this->auditCustomNew = $after;
        Event::dispatch(new AuditCustom($this));
        $this->auditCustomOld = $this->auditCustomNew = [];
        $this->isCustomEvent = false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_zone_id' => 'integer',
            'stock_location_id' => 'integer',
            'fulfilment' => ShippingFulfilment::class,
            'pricing' => ShippingPricing::class,
            'free_over' => MoneyCast::class.':currency',
            'backup' => MoneyCast::class.':currency',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
