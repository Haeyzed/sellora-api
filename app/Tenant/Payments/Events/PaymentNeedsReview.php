<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Payments\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Money arrived that does not match what was expected; it does not count toward its order. Staff refund what was received. Announced only once saved.
 */
final class PaymentNeedsReview implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
