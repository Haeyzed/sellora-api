<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Events;

use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\OrderPaymentTotals;
use App\Tenant\Payments\Models\PaymentRefund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Part of a refund went back to the buyer. Announced inside the transaction that recorded it, with the order's totals, so Orders moves the order's payment status in the same transaction (or neither changes). Listeners that reach outside (emails, credit notes) must be queued to run after commit.
 */
final class RefundSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentRefund $paymentRefund,
        public readonly Order $order,
        public readonly OrderPaymentTotals $totals,
    ) {}
}
