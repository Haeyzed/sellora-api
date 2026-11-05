<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Events;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An exchange's replacement order was paid in full, so what the returned items credited toward it is final (Step 18c): the return it replaces (the hold's reference) can now be settled.
 */
final readonly class ExchangeCreditCommitted
{
    use Dispatchable;

    public function __construct(public CheckoutPaymentHold $hold) {}
}
