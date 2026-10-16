<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One tax charged on one line, or on the shipping, of an order, as it was applied. Never changed or deleted.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $order_line_id Null for tax on shipping.
 * @property string $name
 * @property string $country_code
 * @property string|null $state_code
 * @property Decimal $rate_percent 7.5 means 7.5%.
 * @property string $currency
 * @property Money $tax
 * @property CarbonImmutable $created_at
 */
final class OrderTax extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'order_line_id' => 'integer',
            'rate_percent' => DecimalCast::class.':4',
            'tax' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
