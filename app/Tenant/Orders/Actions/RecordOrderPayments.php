<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Money\Money;
use App\Tenant\Orders\Contracts\OrderAmountHolds;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Enums\OrderPaymentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Events\OrderPaidAfterCancellation;
use App\Tenant\Orders\Events\OrderRefundedBeyondPaid;
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
 * all refunded; what it owes is worked out again each time, from money
 * really taken, never from what a method only holds. An order that drops
 * below paid again (what counted as paid was released, such as a gift card
 * hold that was never made final) loses its paid date; its events keep when
 * it was paid. A cancelled sale that drops to unpaid that way has its invoice
 * credited in full, in the same transaction. Refunds beyond what was paid (a refund that went through
 * after the gateway said it failed, and staff refunded again) are kept as
 * the gateway reports them, and the order is flagged for staff once.
 */
final readonly class RecordOrderPayments
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private MarkOrderAsPaid $markOrderAsPaid,
        private OrderAmountHolds $orderAmountHolds,
        private IssueCreditNote $issueCreditNote,
    ) {}

    public function handle(Order $order, Money $paid, Money $refunded): Order
    {
        return DB::transaction(function () use ($order, $paid, $refunded): Order {
            $locked = $this->orderProgress->lock($order);
            $this->flagIfRefundedBeyondPaid($locked, $paid, $refunded);
            $refundedInAll = $refunded->add($this->orderAmountHolds->refundedFor($locked));
            // What methods chosen at checkout hold for it (such as a gift card) counts as paid, and what they gave back as refunded.
            $status = OrderPaymentStatus::forAmounts($locked->total, $paid->add($this->orderAmountHolds->heldFor($locked)), $refundedInAll);
            // A cancelled order owes back only money really taken (payments, and what methods made final), not what a method merely holds.
            $owes = $locked->status === OrderStatus::Cancelled
                && OrderPaymentStatus::forAmounts($locked->total, $paid->add($this->orderAmountHolds->takenFor($locked)), $refundedInAll)->holdsMoney();

            if ($status === $locked->payment_status && ($locked->status !== OrderStatus::Cancelled || $owes === $locked->refund_due)) {
                return $locked;
            }

            if ($status === OrderPaymentStatus::Paid && ! $locked->payment_status->wasPaidInFull()) {
                return $this->markOrderAsPaid->handle($locked);
            }

            $from = $locked->payment_status;
            $locked->payment_status = $status;

            // Below paid again (what counted as paid was released): it has no paid date any more. Its events keep when it was paid.
            if (! $status->wasPaidInFull()) {
                $locked->paid_at = null;
            }

            if ($locked->status === OrderStatus::Cancelled) {
                $this->owedBackIfAny($locked, $owes);
            }

            $locked->save();

            // A sale undone without a refund (what paid for it was only held, and was released): its invoice is credited in full, in this transaction.
            if ($locked->status === OrderStatus::Cancelled && $status === OrderPaymentStatus::Unpaid && $from->wasPaidInFull()) {
                $this->issueCreditNote->undoSale($locked);
            }

            if ($status !== $from) {
                $this->orderEventLog->record($locked, OrderEventType::PaymentStatusChanged, ['from' => $from->value, 'to' => $status->value]);
            }

            $this->orderProgress->completeIfDone($locked);

            return $locked;
        });
    }

    /**
     * A cancelled order owes back whatever money was really taken for it and not yet given back, and staff are told when it starts to.
     */
    private function owedBackIfAny(Order $locked, bool $owes): void
    {
        if ($owes && ! $locked->refund_due) {
            $this->orderEventLog->record($locked, OrderEventType::PaidAfterCancellation, ['reason' => $locked->cancellation_reason?->value]);
            Log::warning('A payment arrived for a cancelled order; it stays cancelled and the payment is owed back.', ['order' => $locked->public_id]);
            OrderPaidAfterCancellation::dispatch($locked);
        }

        $locked->refund_due = $owes;
    }

    /**
     * Flags the order, once, when more went back than was paid.
     */
    private function flagIfRefundedBeyondPaid(Order $locked, Money $paid, Money $refunded): void
    {
        if ($locked->refunded_beyond_paid || ! $paid->isLessThan($refunded)) {
            return;
        }

        $locked->forceFill(['refunded_beyond_paid' => true])->save();
        $this->orderEventLog->record($locked, OrderEventType::RefundedBeyondPaid, ['paid' => $paid->minorAmount(), 'refunded' => $refunded->minorAmount()]);
        // Only the public ID: staff find the rest from there.
        Log::warning('More was refunded on an order than was paid; staff should look at it.', ['order' => $locked->public_id]);
        OrderRefundedBeyondPaid::dispatch($locked);
    }
}
