<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Money\Money;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves an order's payment status to match what Payments says was paid and refunded, under the order's lock. Payments calls it through its events; nothing else moves an order's payment status.
 *
 * Becoming paid in full runs MarkOrderAsPaid (promotion uses committed,
 * lines that don't ship fulfilled, completion). Money for a cancelled order
 * never revives it: the order stays cancelled and owes it back, until it is
 * all refunded.
 */
final readonly class RecordOrderPayments
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private MarkOrderAsPaid $markOrderAsPaid,
    ) {}

    public function handle(Order $order, Money $paid, Money $refunded): Order
    {
        return DB::transaction(function () use ($order, $paid, $refunded): Order {
            $locked = $this->orderProgress->lock($order);
            $status = OrderPaymentStatus::forAmounts($locked->total, $paid, $refunded);

            if ($status === $locked->payment_status) {
                return $locked;
            }

            if ($status === OrderPaymentStatus::Paid && ! $locked->payment_status->wasPaidInFull()) {
                return $this->markOrderAsPaid->handle($locked);
            }

            $from = $locked->payment_status;
            $locked->payment_status = $status;

            if ($locked->status === OrderStatus::Cancelled) {
                $this->owedBackIfAny($locked);
            }

            $locked->save();
            $this->orderEventLog->record($locked, OrderEventType::PaymentStatusChanged, ['from' => $from->value, 'to' => $status->value]);
            $this->orderProgress->completeIfDone($locked);

            return $locked;
        });
    }

    /**
     * A cancelled order owes back whatever money it still holds, and staff are told when it starts to.
     */
    private function owedBackIfAny(Order $locked): void
    {
        $owes = $locked->payment_status->holdsMoney();

        if ($owes && ! $locked->refund_due) {
            $this->orderEventLog->record($locked, OrderEventType::PaidAfterCancellation, ['reason' => $locked->cancellation_reason?->value]);
            Log::warning('A payment arrived for a cancelled order; it stays cancelled and the payment is owed back.', ['order' => $locked->public_id]);
            OrderPaidAfterCancellation::dispatch($locked);
        }

        $locked->refund_due = $owes;
    }
}
