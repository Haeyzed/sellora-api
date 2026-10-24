<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentGatewayConnection;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The gateway refused the store's keys: the merchant is to reconnect it. Announced once, until the keys work again.
 */
final class PaymentGatewayNeedsReconnecting implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentGatewayConnection $connection) {}
}
