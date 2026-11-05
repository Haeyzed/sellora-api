<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;

/**
 * Records what a payment method holds of an order as it is placed: the one place a hold is written, for checkout and for replacement orders alike.
 *
 * Called inside the placement transaction, so the hold rolls back with the
 * order. From then on the hold follows its lifecycle: committed once the
 * order is paid, released if it expires or is cancelled unpaid.
 */
final readonly class RecordPaymentHold
{
    /**
     * @param  string  $method  The payment method's key, as its extension handles it.
     * @param  string|null  $reference  The method's own reference for what it held, if it has one.
     */
    public function handle(Order $order, string $method, Money $amount, ?string $reference): CheckoutPaymentHold
    {
        $hold = new CheckoutPaymentHold;
        $hold->forceFill([
            'order_id' => $order->id,
            'method' => $method,
            'reference' => $reference,
            'amount' => $amount,
            'status' => PaymentHoldStatus::Held,
        ])->save();

        return $hold;
    }
}
