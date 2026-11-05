<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\Refund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A refund to a module's destination definitely failed, so nothing was paid out: the module refused it ("refund_destination_refused"), no module registers its destination any more ("destination_not_registered"), or its module is unavailable to the store ("destination_unavailable"). Each part also announced RefundFailed. For staff to look at, and the customer can choose again. Announced only once saved.
 */
final class RefundDestinationFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Refund $refund,
        public readonly string $failureCode,
    ) {}
}
