<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Events;

use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefundChoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The customer chose where a return's refund goes, or staff recorded their choice. For buyer and staff messages (Messaging, Step 20). Announced only once saved.
 */
final class ReturnRefundDestinationChosen implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly OrderReturn $return,
        public readonly ReturnRefundChoice $choice,
    ) {}
}
