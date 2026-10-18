<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A gateway reported that a buyer disputed a payment with their bank, for staff alerts (Messaging, Step 20). Nothing about the payment or its order changes; disputes are handled later. Announced only once recorded.
 */
final class PaymentDisputed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Payment|null  $payment  Null when the dispute names no payment of ours.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly ?Payment $payment,
    ) {}
}
