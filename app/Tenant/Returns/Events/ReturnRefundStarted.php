<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payments made a refund for an attempt to refund a return, once per attempt; never for an attempt Payments refused. For buyer and staff messages (Messaging, Step 20). Announced only once saved.
 */
final class ReturnRefundStarted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly OrderReturn $return,
        public readonly ReturnRefund $attempt,
    ) {}
}
