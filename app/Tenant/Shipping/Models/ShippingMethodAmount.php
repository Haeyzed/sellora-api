<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;

/**
 * A method's exact "free over" threshold, or backup price, in one offered currency, used as it is instead of converting the base amount.
 *
 * @property int $id
 * @property int $shipping_method_id
 * @property string $currency_code ISO 4217; never the base currency.
 * @property Money|null $free_over
 * @property Money|null $backup A carrier-priced method's backup price.
 */
final class ShippingMethodAmount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'currency_code',
        'free_over',
        'backup',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_method_id' => 'integer',
            'free_over' => MoneyCast::class.':currency_code',
            'backup' => MoneyCast::class.':currency_code',
        ];
    }
}
