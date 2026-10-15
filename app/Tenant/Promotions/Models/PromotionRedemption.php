<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of a promotion by a checkout or an order, with what it took off. Usage limits are counted from these rows (section 13).
 *
 * @property int $id
 * @property string $public_id
 * @property int $promotion_id
 * @property int|null $promotion_code_id
 * @property string $holder_type Such as "checkout" or "order".
 * @property string $holder_id The holder's public ID.
 * @property int|null $customer_id
 * @property string|null $email In lower case: the customer's or the guest's.
 * @property RedemptionStatus $status
 * @property string $currency
 * @property Money $discount What the promotion took off, in the order's currency.
 * @property CarbonImmutable|null $expires_at A checkout's hold; null once committed.
 * @property CarbonImmutable|null $settled_at When it was committed or released.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Promotion $promotion
 * @property-read PromotionCode|null $code
 * @property-read Customer|null $customer
 */
final class PromotionRedemption extends Model
{
    use HasPublicId;

    /**
     * Dates keep their offset when saved.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PromotionCode, $this>
     */
    public function code(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class, 'promotion_code_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Uses that count against limits at the moment: committed ones, and reserved ones whose hold hasn't run out.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCounting(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query->where(static fn (Builder $live) => $live->where('status', RedemptionStatus::Committed->value)
            ->orWhere(static fn (Builder $held) => $held->where('status', RedemptionStatus::Reserved->value)->where(static fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', $at))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RedemptionStatus::class,
            'discount' => MoneyCast::class.':currency',
            'expires_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
