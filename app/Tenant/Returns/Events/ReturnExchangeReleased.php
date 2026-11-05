<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A return's exchange was released: its replacement order expired or was cancelled unpaid, so the return is back where it was, free for another exchange or a refund (Step 18d). Announced once the change is committed, for Messaging (Step 20) to tell the store.
 */
final class ReturnExchangeReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly OrderReturn $return) {}
}
