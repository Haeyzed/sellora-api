<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order was paid and fulfilled (or had nothing to fulfil). Announced only once it is saved.
 */
final class OrderCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
