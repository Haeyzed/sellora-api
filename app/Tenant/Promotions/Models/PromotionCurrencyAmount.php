<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A promotion's exact amounts in one offered currency, used instead of converting the base amounts, like a fixed price.
 *
 * Each amount is optional: one left out is converted from the base amount at
 * the rate the checkout uses.
 *
 * @property int $id
 * @property int $promotion_id
 * @property string $currency_code ISO 4217; never the base currency.
 * @property Money|null $discount
 * @property Money|null $max_discount
 * @property Money|null $min_subtotal
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PromotionCurrencyAmount extends Model
{
    protected $fillable = [
        'currency_code',
        'discount',
        'max_discount',
        'min_subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount' => MoneyCast::class.':currency_code',
            'max_discount' => MoneyCast::class.':currency_code',
            'min_subtotal' => MoneyCast::class.':currency_code',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
