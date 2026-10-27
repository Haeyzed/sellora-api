<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\PaymentActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A driver recording the cash they collected for an order paid on delivery, for Delivery (Step 17). It counts as the order's pending cash payment received, as RecordManualPayment records it, confirmed by the driver.
 *
 * Locks the order, then its pending cash payment, inside the caller's
 * transaction when there is one, so Delivery writes the driver's cash
 * ledger in the same transaction. Less than expected leaves the rest
 * pending; more is recorded and owed back. An order with no cash pending
 * (already collected, paid another way, or a test order, which is never paid
 * in cash) is refused.
 */
final readonly class RecordCashCollectedOnDelivery
{
    public function __construct(
        private RecordManualPayment $recordManualPayment,
        private PaymentActivity $paymentActivity,
    ) {}

    /**
     * @param  Model  $collector  The driver who took the cash.
     * @return Payment The payment it was recorded on.
     *
     * @throws PaymentNotAwaitingReceiptException When the order has no cash pending.
     * @throws PaymentReceiptInvalidException When what was collected isn't a positive amount in the order's currency.
     */
    public function handle(Model $collector, Order $order, Money $collected): Payment
    {
        $recorded = DB::transaction(function () use ($collector, $order, $collected): Payment {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->is_test ? null : Payment::query()
                ->where('order_id', $locked->id)
                ->where('method', PaymentMethod::CashOnDelivery->value)
                ->where('status', PaymentStatus::Pending->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw new PaymentNotAwaitingReceiptException;
            }

            return $this->recordManualPayment->record(AccountReference::to($collector), $payment, $collected, method: PaymentMethod::CashOnDelivery);
        });

        $this->paymentActivity->record($collector, $recorded, 'cash_collected', 'Collected cash on delivery for');

        return $recorded;
    }
}
