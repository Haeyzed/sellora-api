<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Events;

use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A delivery was handed over, with its proof. Announced only once it is saved.
 */
final class DeliveryCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Delivery $delivery) {}
}
