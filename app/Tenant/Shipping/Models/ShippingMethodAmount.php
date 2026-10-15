<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * A method's exact "free over" threshold in one offered currency, used as it is instead of converting the base threshold.
 *
 * @property int $id
 * @property int $shipping_method_id
 * @property string $currency_code ISO 4217; never the base currency.
 * @property Money $free_over
 */
final class ShippingMethodAmount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'currency_code',
        'free_over',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_method_id' => 'integer',
            'free_over' => MoneyCast::class.':currency_code',
        ];
    }
}
