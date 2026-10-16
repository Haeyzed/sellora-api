<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Promotions\Enums\DiscountClass;
use App\Tenant\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one discount took off one line, or off the shipping, on an order. Never changed or deleted.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $order_line_id Null for a discount on shipping.
 * @property string $source Where it came from, such as "promotion".
 * @property string $reference What it is in that source, such as the promotion's public ID.
 * @property int|null $promotion_id
 * @property string|null $code The code entered, when it came from one.
 * @property string $label What the buyer saw, in the order's language.
 * @property DiscountClass $class
 * @property string $currency
 * @property Money $discount
 * @property CarbonImmutable $created_at
 * @property-read Promotion|null $promotion
 */
final class OrderDiscount extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'order_line_id' => 'integer',
            'promotion_id' => 'integer',
            'class' => DiscountClass::class,
            'discount' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
