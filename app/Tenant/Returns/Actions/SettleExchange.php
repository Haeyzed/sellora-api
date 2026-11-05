<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\PaymentMethods\ExchangeCreditPaymentMethod;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\RefundDestinations;
use App\Tenant\Returns\Enums\ReturnExchangeStatus;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnExchange;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Services\ReturnProgress;
use Illuminate\Support\Facades\DB;

/**
 * Settles a return's exchange once its replacement order's credit is final or given back (Step 18d).
 *
 * Paid: the original order gets a refund of the returned lines to core's
 * exchange destination, for what they credited toward the replacement
 * (anything else is kept back from it, so its credit note credits exactly
 * that), under the exchange's own key, so it is never made twice. A cheaper
 * replacement's difference then goes back to the buyer, by amount, to the
 * destination they chose, through the return's refund attempts; the return
 * completes once it did, or at once when there is none.
 *
 * Expired or cancelled unpaid: the exchange is released, and the return goes
 * back to where it was (inspected, or approved with the buyer keeping the
 * items), free for another exchange or a refund.
 */
final readonly class SettleExchange
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private RefundOrder $refundOrder,
        private RefundReturn $refundReturn,
    ) {}

    /**
     * Its replacement was paid. Safe to call again: each step is done once.
     *
     * @throws ReturnTransitionNotAllowedException When its replacement isn't paid yet.
     * @throws DomainException What Payments refused the refund or the difference with; the exchange stays as it was, to be settled again.
     */
    public function committed(ReturnExchange $exchange): void
    {
        $this->ensureCreditIsFinal($exchange);
        $return = OrderReturn::query()->findOrFail($exchange->return_id);
        $actor = StaffMember::query()->where('public_id', $exchange->confirmed_by_id)->firstOrFail();

        // 1. The original order's refund to the exchange, outside any transaction: Payments pays it out after its own commit.
        $refund = $exchange->refund_id === null ? $this->refundToExchange($return, $exchange, $actor) : Refund::query()->findOrFail($exchange->refund_id);

        // 2. The exchange final, and the return completed when nothing goes back.
        $exchange = DB::transaction(function () use ($return, $exchange, $refund): ReturnExchange {
            $locked = $this->returnProgress->lock($return);
            $settled = ReturnExchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();

            if ($settled->status === ReturnExchangeStatus::Pending) {
                $settled->forceFill(['status' => ReturnExchangeStatus::Committed, 'committed_at' => now(), 'refund_id' => $refund->id])->save();
            }

            if ($locked->status === ReturnStatus::AwaitingExchange && ! $settled->difference->isPositive()) {
                $this->returnProgress->move($locked, ReturnStatus::Completed, null);
            }

            return $settled;
        });

        // 3. The difference to the buyer, as a refund attempt of the return.
        if ($exchange->difference->isPositive()) {
            $this->refundReturn->giveExchangeDifference($return, $actor);
        }
    }

    /**
     * Its replacement expired or was cancelled unpaid.
     */
    public function released(ReturnExchange $exchange): void
    {
        DB::transaction(function () use ($exchange): void {
            $locked = $this->returnProgress->lock(OrderReturn::query()->findOrFail($exchange->return_id));
            $released = ReturnExchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();

            if ($released->status !== ReturnExchangeStatus::Pending) {
                return;
            }

            $released->forceFill(['status' => ReturnExchangeStatus::Released, 'released_at' => now()])->save();

            if ($locked->status === ReturnStatus::AwaitingExchange) {
                $this->returnProgress->move($locked, $locked->inspected_at === null ? ReturnStatus::Approved : ReturnStatus::Inspected, null);
            }
        });
    }

    /**
     * Only a replacement that was paid settles the return: until then nothing is refunded.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    private function ensureCreditIsFinal(ReturnExchange $exchange): void
    {
        $isFinal = CheckoutPaymentHold::query()
            ->where('order_id', $exchange->order_id)
            ->where('method', ExchangeCreditPaymentMethod::KEY)
            ->whereIn('status', [PaymentHoldStatus::Committed->value, PaymentHoldStatus::Refunded->value])
            ->exists();

        if (! $isFinal && $exchange->credit->isPositive()) {
            throw new ReturnTransitionNotAllowedException;
        }
    }

    private function refundToExchange(OrderReturn $return, ReturnExchange $exchange, StaffMember $actor): Refund
    {
        $order = Order::query()->findOrFail($return->order_id);
        $replacement = Order::query()->findOrFail($exchange->order_id);
        $keptBack = $exchange->returned_value->subtract($exchange->credit);
        $lines = $return->lines()->with('orderLine')->get()->mapWithKeys(static fn (ReturnLine $line): array => [$line->orderLine->public_id => $line->quantity])->all();

        return $this->refundOrder->handle($actor, $order, new RefundRequest(
            lines: $lines,
            reason: 'Exchange of return '.$return->displayNumber().' for order '.$replacement->displayNumber(),
            idempotencyKey: 'exchange:'.$exchange->public_id,
            withheld: $keptBack->isPositive() ? $keptBack : null,
            withheldReason: $keptBack->isPositive() ? 'Not used by replacement order '.$replacement->displayNumber() : null,
            destination: RefundDestinations::EXCHANGE,
        ));
    }
}
