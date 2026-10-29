<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Tenant\Payments\Models\Refund;
use App\Tenant\Returns\Enums\ReturnRefundStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attempt to refund a return through Payments, saved before Payments is asked, with exactly what it asks for.
 *
 * @property int $id
 * @property int $return_id
 * @property int $attempt From 1.
 * @property string $idempotency_key "return:{return public ID}:{attempt}".
 * @property int|null $refund_id Null until Payments answered, and when it refused.
 * @property ReturnRefundStatus $status
 * @property string|null $refusal_code
 * @property bool $shipping
 * @property int $withheld_amount In minor units of the order's currency.
 * @property string|null $withheld_reason
 * @property string $requested_by_type
 * @property string $requested_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrderReturn $orderReturn
 * @property-read Refund|null $refund
 */
final class ReturnRefund extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sP';

    public static function keyFor(OrderReturn $return, int $attempt): string
    {
        return "return:{$return->public_id}:{$attempt}";
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_id' => 'integer',
            'attempt' => 'integer',
            'refund_id' => 'integer',
            'status' => ReturnRefundStatus::class,
            'shipping' => 'boolean',
            'withheld_amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
