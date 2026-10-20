<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Data\RefundedLine;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Exceptions\OrderNotRefundableException;
use App\Tenant\Payments\Exceptions\PartialRefundNotSupportedException;
use App\Tenant\Payments\Exceptions\PaymentGatewayNotConnectedException;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Payments\Exceptions\RefundInvalidException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Payments\Models\RefundLine;
use App\Tenant\Payments\PaymentGatewayRegistry;
use App\Tenant\Payments\Services\PaymentActivity;
use App\Tenant\Payments\Services\PaymentEventLog;
use App\Tenant\Payments\Services\RefundAmounts;
use App\Tenant\Payments\Services\RefundChecks;
use App\Tenant\Payments\Services\RefundSettlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff giving money back on an order (payments.refund): an amount, or lines and shipping at what the buyer paid for them, tax included. Refunds never put stock back; Returns does (Step 18).
 *
 * Which payments it draws on: the one staff name, or else the newest
 * succeeded payments first, each up to what it can still give back (what it
 * received, less its refunds pending or succeeded). A payment in review is
 * refunded only when named, for all it received; it never counts toward the
 * order either way. An open order must have been paid in full; a cancelled
 * one gives back whatever it holds.
 *
 * The limit is read under the order's lock, then the payments' (always in
 * that order, as everywhere else), so two refunds at once can never give
 * back more than was paid. Each part goes back the way its payment came:
 * through its gateway, after commit, with our reference as its idempotency
 * key (pending until the gateway says it went through), or for a payment
 * made by hand, recorded as paid back by hand at once. The same
 * Idempotency-Key returns the same refund.
 */
final readonly class RefundOrder
{
    public function __construct(
        private RefundAmounts $refundAmounts,
        private RefundSettlement $refundSettlement,
        private RefundChecks $refundChecks,
        private SendRefundToGateway $sendRefundToGateway,
        private PaymentGatewayRegistry $registry,
        private PaymentEventLog $paymentEventLog,
        private PaymentActivity $paymentActivity,
    ) {}

    /**
     * @throws OrderNotRefundableException
     * @throws RefundInvalidException
     * @throws RefundExceedsRefundableException
     * @throws PartialRefundNotSupportedException
     * @throws PaymentGatewayNotConnectedException When a payment drawn on came through a gateway that is no longer connected.
     */
    public function handle(StaffMember $actor, Order $order, RefundRequest $request): Refund
    {
        $this->ensureRequestIsWhole($request);
        [$refund, $isNew] = DB::transaction(fn (): array => $this->record(AccountReference::to($actor), $order, $request));

        if (! $isNew) {
            return $refund;
        }

        foreach ($refund->paymentRefunds->where('status', RefundStatus::Pending) as $paymentRefund) {
            // Before calling the gateway, so the refund is checked on even if this request never finishes.
            $this->refundChecks->scheduleFirst($paymentRefund);
            $this->sendRefundToGateway->handle($paymentRefund);
        }

        $this->paymentActivity->record($actor, $refund, 'refund_created', 'Refunded part or all of order '.$order->displayNumber());

        return $refund->refresh()->load(['lines', 'paymentRefunds']);
    }

    /**
     * An amount, or lines and shipping, never both and never nothing.
     *
     * @throws RefundInvalidException
     */
    private function ensureRequestIsWhole(RefundRequest $request): void
    {
        $byItems = $request->lines !== [] || $request->shipping;
        $amountIsValid = $request->amount === null || $request->amount->isPositive();

        if ($byItems === $request->isByAmount() || ! $amountIsValid) {
            throw new RefundInvalidException;
        }
    }

    /**
     * @return array{Refund, bool} The refund, and whether it was made now.
     */
    private function record(AccountReference $actor, Order $order, RefundRequest $request): array
    {
        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $earlier = $request->idempotencyKey === null ? null : Refund::query()->where('order_id', $locked->id)->where('idempotency_key', $request->idempotencyKey)->first();

        if ($earlier !== null) {
            return [$earlier->load(['lines', 'paymentRefunds']), false];
        }

        $payments = Payment::query()->where('order_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
        $named = $this->namedPayment($payments, $request);

        if ($named?->status === PaymentStatus::NeedsReview) {
            return [$this->refundReview($actor, $locked, $named, $request), true];
        }

        $this->ensureOrderHoldsMoney($locked);
        $lines = $this->refundAmounts->lines($locked, $request);
        [$shipping, $shippingTax] = $this->refundAmounts->shipping($locked, $request);
        $amount = $request->amount ?? Money::sum($locked->currency, [$shipping, ...array_map(static fn (RefundedLine $line): Money => $line->amount, $lines)]);

        if (! $amount->hasSameCurrencyAs($locked->total)) {
            throw new RefundInvalidException;
        }

        $draws = $this->draws($named === null ? $payments->where('status', PaymentStatus::Succeeded)->sortByDesc('id') : collect([$named]), $amount);
        $refund = $this->createRefund($actor, $locked, $amount, $shipping, $shippingTax, $request);
        $this->createLines($refund, $lines);
        $this->createParts($actor, $refund, $locked, $draws);

        return [$refund->load(['lines', 'paymentRefunds']), true];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     *
     * @throws RefundInvalidException
     */
    private function namedPayment(Collection $payments, RefundRequest $request): ?Payment
    {
        if ($request->paymentId === null) {
            return null;
        }

        $payment = $payments->firstWhere('public_id', $request->paymentId);

        if ($payment === null || ! in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::NeedsReview], true)) {
            throw new RefundInvalidException;
        }

        return $payment;
    }

    /**
     * Money in review is given back all at once, exactly as it arrived; it never counted toward the order.
     *
     * @throws RefundInvalidException
     * @throws RefundExceedsRefundableException
     */
    private function refundReview(AccountReference $actor, Order $order, Payment $payment, RefundRequest $request): Refund
    {
        $received = $payment->received ?? throw new RefundInvalidException;

        if ($request->lines !== [] || $request->shipping || ($request->amount !== null && ! ($request->amount->hasSameCurrencyAs($received) && $request->amount->isEqualTo($received)))) {
            throw new RefundInvalidException;
        }

        $draws = $this->draws(collect([$payment]), $received);
        $refund = $this->createRefund($actor, $order, $received, Money::zero($received->currency()), Money::zero($received->currency()), $request);
        $this->createParts($actor, $refund, $order, $draws);

        return $refund->load(['lines', 'paymentRefunds']);
    }

    /**
     * @throws OrderNotRefundableException
     */
    private function ensureOrderHoldsMoney(Order $order): void
    {
        $holds = $order->payment_status->wasPaidInFull() || ($order->status === OrderStatus::Cancelled && $order->payment_status->holdsMoney());

        if (! $holds) {
            throw new OrderNotRefundableException;
        }
    }

    /**
     * How much each payment gives back, filling each in turn up to what it can still give back.
     *
     * @param  iterable<Payment>  $payments  Already locked, in the order to draw on them.
     * @return list<array{Payment, Money}>
     *
     * @throws RefundExceedsRefundableException
     * @throws PartialRefundNotSupportedException
     * @throws PaymentGatewayNotConnectedException
     */
    private function draws(iterable $payments, Money $amount): array
    {
        $left = $amount;
        $draws = [];

        foreach ($payments as $payment) {
            $available = $this->stillRefundable($payment);

            if (! $left->isPositive() || ! $available->isPositive()) {
                continue;
            }

            $draw = $left->isLessThan($available) ? $left : $available;
            $this->ensureGatewayCanRefund($payment, $draw);
            $draws[] = [$payment, $draw];
            $left = $left->subtract($draw);
        }

        if ($left->isPositive()) {
            throw new RefundExceedsRefundableException;
        }

        return $draws;
    }

    /**
     * What it received, less its refunds pending or succeeded.
     */
    private function stillRefundable(Payment $payment): Money
    {
        $received = $payment->received ?? Money::zero($payment->currency);
        $held = PaymentRefund::query()->where('payment_id', $payment->id)->whereIn('status', RefundStatus::holdingMoney())->sum('amount_amount');

        return $received->subtract(Money::ofMinor((int) $held, $received->currency()));
    }

    /**
     * @throws PartialRefundNotSupportedException
     * @throws PaymentGatewayNotConnectedException
     */
    private function ensureGatewayCanRefund(Payment $payment, Money $draw): void
    {
        if ($payment->method !== PaymentMethod::Gateway) {
            return;
        }

        $connection = $payment->connection;

        if ($connection === null || ! $connection->isConnected()) {
            throw new PaymentGatewayNotConnectedException;
        }

        $isWhole = $payment->received !== null && $draw->isEqualTo($payment->received);

        if (! $isWhole && ! $this->registry->get($connection->gateway)->capabilities()->partialRefunds) {
            throw new PartialRefundNotSupportedException;
        }
    }

    private function createRefund(AccountReference $actor, Order $order, Money $amount, Money $shipping, Money $shippingTax, RefundRequest $request): Refund
    {
        $refund = new Refund;
        $refund->forceFill([
            'order_id' => $order->id,
            'amount' => $amount,
            'shipping' => $shipping,
            'shipping_tax' => $shippingTax,
            'reason' => $request->reason,
            'idempotency_key' => $request->idempotencyKey,
            'requested_by_type' => $actor->type,
            'requested_by_id' => $actor->publicId,
        ])->save();

        return $refund;
    }

    /**
     * @param  list<RefundedLine>  $lines
     */
    private function createLines(Refund $refund, array $lines): void
    {
        foreach ($lines as $line) {
            (new RefundLine)->forceFill([
                'refund_id' => $refund->id,
                'order_line_id' => $line->line->id,
                'quantity' => $line->quantity,
                'amount' => $line->amount,
                'tax' => $line->tax,
            ])->save();
        }
    }

    /**
     * One part per payment drawn on: pending for a gateway (sent after commit), paid back by hand at once otherwise.
     *
     * @param  list<array{Payment, Money}>  $draws
     */
    private function createParts(AccountReference $actor, Refund $refund, Order $order, array $draws): void
    {
        foreach ($draws as [$payment, $draw]) {
            $byHand = $payment->method !== PaymentMethod::Gateway;
            $part = new PaymentRefund;
            $part->forceFill([
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'status' => RefundStatus::Pending,
                'amount' => $draw,
                'reference' => 'rfd_'.Str::lower((string) Str::ulid()),
                'recorded_by_type' => $byHand ? $actor->type : null,
                'recorded_by_id' => $byHand ? $actor->publicId : null,
            ])->save();
            $this->paymentEventLog->record($payment, PaymentEventType::RefundStarted, ['refund' => $part->public_id, 'amount' => $draw->minorAmount()], $actor);

            if ($byHand) {
                $this->refundSettlement->recordByHand($part, $payment, $order, $actor);
            }
        }
    }
}
