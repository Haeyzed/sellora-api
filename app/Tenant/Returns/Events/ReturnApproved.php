<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff agreed to a return: the items are on their way back, or the buyer keeps them. Announced once the change is committed, for Messaging (Step 20) to tell the buyer and the store.
 */
final class ReturnApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly OrderReturn $return) {}
}
