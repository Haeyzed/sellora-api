<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a gateway connection still has money in motion: payments that may still go through, or refunds still on their way back. Such a connection can't be disconnected, or have its keys replaced by another account's or the other mode's.
 */
final readonly class MoneyInMotion
{
    public function on(PaymentGatewayConnection $connection): bool
    {
        return Payment::query()->where('connection_id', $connection->id)->whereIn('status', PaymentStatus::inMotion())->exists()
            || PaymentRefund::query()
                ->where('status', RefundStatus::Pending->value)
                ->whereHas('payment', static fn (Builder $payment) => $payment->where('connection_id', $connection->id))
                ->exists();
    }
}
