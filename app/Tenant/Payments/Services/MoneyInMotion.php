<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Services;

use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;

/**
 * Whether a gateway connection still has money in motion: payments that may still go through. Such a connection can't be disconnected, or have its keys replaced by another account's or the other mode's.
 */
final readonly class MoneyInMotion
{
    public function on(PaymentGatewayConnection $connection): bool
    {
        return Payment::query()->where('connection_id', $connection->id)->whereIn('status', PaymentStatus::inMotion())->exists();
    }
}
