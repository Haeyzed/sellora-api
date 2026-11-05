<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\Refund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A module's destination paid a refund out to the customer, such as crediting their store credit; each part also announced RefundSucceeded. For buyer and staff messages (Messaging, Step 20). Announced only once saved.
 */
final class RefundGivenToDestination implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Refund $refund) {}
}
