<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Orders\Actions\RecordOrderPayments;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Services\OrderPayments;

/**
 * Moves an order's payment status to what its payments, and what payment methods chosen at checkout hold for it, add up to: for Checkout (Step 16) when those methods (such as a gift card) cover the whole order at placement, with no payment to wait for. Payment status is still moved only through Payments.
 */
final readonly class RecalculateOrderPayments
{
    public function __construct(
        private OrderPayments $orderPayments,
        private RecordOrderPayments $recordOrderPayments,
    ) {}

    public function handle(Order $order): Order
    {
        $totals = $this->orderPayments->totals($order);

        return $this->recordOrderPayments->handle($order, $totals->paid, $totals->refunded);
    }
}
