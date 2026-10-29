<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Events;

use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Committing or releasing what a payment method holds for an order failed checkout.hold_settle_alert_after tries in a row (why is on the hold): staff are alerted once, and it is still tried again hourly until it settles.
 */
final readonly class PaymentHoldKeepsFailing
{
    use Dispatchable;

    public function __construct(public CheckoutPaymentHold $hold) {}
}
