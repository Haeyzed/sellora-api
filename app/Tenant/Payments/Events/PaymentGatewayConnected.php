<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The store connected a gateway, or replaced its credentials. Announced only once saved.
 */
final class PaymentGatewayConnected implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentGatewayConnection $connection) {}
}
