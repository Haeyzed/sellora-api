<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The gateway reported part of a refund as failed, then as gone through. It counts as refunded (the money moved), and staff are told, since they may have refunded again after the failure. For staff alerts (Messaging, Step 20). Announced only once saved.
 */
final class RefundSucceededAfterFailing implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PaymentRefund $paymentRefund) {}
}
