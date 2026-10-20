<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Shared\Money\Money;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * More was paid by hand than was expected: it all counts toward the order, and staff are told the difference is owed back (Messaging, Step 20). Announced only once saved.
 */
final class PaymentOverpaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Money $excess,
    ) {}
}
