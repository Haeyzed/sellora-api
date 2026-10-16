<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment arrived for an order already cancelled: it stays cancelled and the payment is owed back. For staff alerts (Messaging, Step 20). Announced only once it is saved.
 */
final class OrderPaidAfterCancellation implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
