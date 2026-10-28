<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Events;

use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver picked up a delivery, or set out with it again after a failed attempt. Announced only once it is saved.
 */
final class DeliveryPickedUp implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Delivery $delivery) {}
}
