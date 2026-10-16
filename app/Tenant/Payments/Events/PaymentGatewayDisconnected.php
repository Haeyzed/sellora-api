<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The store disconnected a gateway: its credentials are gone. Announced only once saved.
 */
final class PaymentGatewayDisconnected implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentGatewayConnection $connection) {}
}
