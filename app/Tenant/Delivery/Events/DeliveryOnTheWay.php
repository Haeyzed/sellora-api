<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Events;

use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver set off with a delivery: its buyer can now follow it. Announced only once it is saved.
 */
final class DeliveryOnTheWay implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Delivery $delivery) {}
}
