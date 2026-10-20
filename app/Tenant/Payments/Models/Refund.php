<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Shared\Concerns\HasPublicId;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * What staff gave back on an order (section 3.3): an amount, or lines and shipping at what the buyer paid for them, tax included. A financial record: written once, never changed or deleted (the database refuses it).
 *
 * It draws on one or more of the order's payments, each part going back the
 * way that payment came; where each part stands is in its payment refunds.
 *
 * @property int $id
 * @property string $public_id
 * @property int $order_id
 * @property string $currency The order's.
 * @property Money $amount All of it: lines, shipping, or an amount.
 * @property Money $shipping The part for shipping, its tax included; zero without.
 * @property Money $shipping_tax
 * @property string|null $reason
 * @property string|null $idempotency_key The client's.
 * @property string $requested_by_type By morph name.
 * @property string $requested_by_id
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read Collection<int, RefundLine> $lines
 * @property-read Collection<int, PaymentRefund> $paymentRefunds
 */
final class Refund extends Model implements AuditableContract
{
    use Auditable;
    use HasPublicId;

    public const UPDATED_AT = null;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<RefundLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RefundLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function paymentRefunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':currency',
            'shipping' => MoneyCast::class.':currency',
            'shipping_tax' => MoneyCast::class.':currency',
            'created_at' => 'immutable_datetime',
        ];
    }
}
