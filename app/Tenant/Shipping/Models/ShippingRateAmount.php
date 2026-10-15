<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * A bracket's exact price in one offered currency, used as it is instead of converting the base price, like a fixed price.
 *
 * @property int $id
 * @property int $shipping_rate_id
 * @property string $currency_code ISO 4217; never the base currency.
 * @property Money $price
 */
final class ShippingRateAmount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'currency_code',
        'price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_rate_id' => 'integer',
            'price' => MoneyCast::class.':currency_code',
        ];
    }
}
