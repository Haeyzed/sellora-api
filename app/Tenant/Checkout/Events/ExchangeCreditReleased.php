<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Events;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An exchange's replacement order expired or was cancelled unpaid, so what the returned items credited toward it is given back (Step 18c): the return it replaces (the hold's reference) is free for another replacement, or a refund.
 */
final readonly class ExchangeCreditReleased
{
    use Dispatchable;

    public function __construct(public CheckoutPaymentHold $hold) {}
}
