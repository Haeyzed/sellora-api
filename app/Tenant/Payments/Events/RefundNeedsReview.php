<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Part of a refund needs staff: the gateway's answers can't tell whether it was made, so it is never sent again (RefundReviewReason), for staff alerts (Messaging, Step 20). Announced once per part, once saved.
 */
final class RefundNeedsReview implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentRefund $paymentRefund) {}
}
