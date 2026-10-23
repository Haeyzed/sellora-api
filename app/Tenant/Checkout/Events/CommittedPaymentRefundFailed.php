<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Events;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment method couldn't give back what it took for a cancelled order (why is on the hold): the order still owes the refund, for staff to see, and it is tried again hourly.
 */
final readonly class CommittedPaymentRefundFailed
{
    use Dispatchable;

    public function __construct(public CheckoutPaymentHold $hold) {}
}
