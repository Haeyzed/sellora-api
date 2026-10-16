<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An order was paid for. Announced only once it is saved.
 */
final class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
