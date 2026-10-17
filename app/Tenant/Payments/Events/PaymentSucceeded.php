<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\OrderPaymentTotals;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment succeeded: the money arrived exactly as expected. Announced inside the transaction that recorded it, with the order's totals, so Orders moves the order's payment status in the same transaction (or neither changes). Listeners that reach outside (emails, messages) must be queued to run after commit.
 */
final class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly Order $order,
        public readonly OrderPaymentTotals $totals,
    ) {}
}
