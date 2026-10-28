<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Events;

use App\Tenant\Delivery\Models\Delivery;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A delivery failed; the driver keeps the goods until staff resolve it. Announced only once it is saved.
 */
final class DeliveryFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Delivery $delivery) {}
}
