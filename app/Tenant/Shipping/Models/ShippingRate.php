<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One price bracket of a method: what it costs for a weight or order value in [from, to). Not audited on its own: its method records the whole list.
 *
 * @property int $id
 * @property int $shipping_method_id
 * @property int $range_from Grams, or the base currency's minor units; included.
 * @property int|null $range_to Not included; null for "and up".
 * @property int $amount The price in the base currency's minor units.
 * @property-read Collection<int, ShippingRateAmount> $currencyAmounts
 */
final class ShippingRate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'range_from',
        'range_to',
        'amount',
    ];

    /**
     * Whether a weight or order value falls in it.
     */
    public function covers(int $value): bool
    {
        return $value >= $this->range_from && ($this->range_to === null || $value < $this->range_to);
    }

    /**
     * @return HasMany<ShippingRateAmount, $this>
     */
    public function currencyAmounts(): HasMany
    {
        return $this->hasMany(ShippingRateAmount::class)->orderBy('currency_code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_method_id' => 'integer',
            'range_from' => 'integer',
            'range_to' => 'integer',
            'amount' => 'integer',
        ];
    }
}
