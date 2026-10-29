<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff refused a return, with a reason: when it was asked for, or after inspecting the items. Announced once the change is committed, for Messaging (Step 20) to tell the buyer and the store.
 */
final class ReturnRejected implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly OrderReturn $return) {}
}
