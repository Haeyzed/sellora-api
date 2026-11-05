<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Staff confirmed a return's exchange: its replacement order is placed, and the return waits for it to be paid (Step 18d). Announced once the change is committed, for Messaging (Step 20) to tell the buyer what, if anything, they still owe.
 */
final class ReturnExchangeConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly OrderReturn $return) {}
}
