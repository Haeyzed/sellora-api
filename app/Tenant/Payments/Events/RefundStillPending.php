<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Part of a refund is still pending at the gateway days after it was sent (config payments.checks.refund_daily_after_days), for staff alerts (Messaging, Step 20). Announced once per part, once saved.
 */
final class RefundStillPending implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentRefund $paymentRefund) {}
}
