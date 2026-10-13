<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Models;

use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A currency the store sells in besides its base currency, such as USD for a Nigerian store, with the merchant's margin or manual rate.
 *
 * The store's rate is the market cross rate × (1 + margin / 100), or the
 * manual rate when one is set; the two are never multiplied into one stored
 * number (section 3.3). Addressed by its ISO code. Audited, so every margin
 * and manual rate change is on record.
 *
 * @property int $id
 * @property string $currency_code ISO 4217; never the base currency.
 * @property bool $is_active
 * @property Decimal $margin_percent From 0 to 25, four decimal places, added to the market rate.
 * @property Decimal|null $manual_rate Units of this currency per unit of the base currency, twelve decimal places; used instead of the market rate when set.
 * @property CarbonImmutable|null $manual_rate_set_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class StoreCurrency extends Model implements AuditableContract
{
    use Auditable;

    /** The highest margin a merchant may add to the market rate, in percent. */
    public const int MAX_MARGIN_PERCENT = 25;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'margin_percent' => '0',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id'];

    public function getRouteKeyName(): string
    {
        return 'currency_code';
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'margin_percent' => DecimalCast::class.':4',
            'manual_rate' => DecimalCast::class.':12',
            'manual_rate_set_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
