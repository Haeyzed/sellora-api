<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;

/**
 * How much cash a driver should collect for an order on delivery, for Delivery (Step 17): what its pending cash payment expects, or null when nothing is to be collected (paid another way, already collected, or a test order).
 */
final readonly class FindCashToCollect
{
    public function handle(Order $order): ?Money
    {
        if ($order->is_test || ! $order->pay_on_delivery) {
            return null;
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('method', PaymentMethod::CashOnDelivery->value)
            ->where('status', PaymentStatus::Pending->value)
            ->orderByDesc('id')
            ->first();

        return $payment?->amount;
    }
}
