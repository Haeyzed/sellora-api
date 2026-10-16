<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order was cancelled, by staff, its customer, or because it wasn't paid in time; its stock and promotion uses were given back. Announced only once it is saved.
 */
final class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
