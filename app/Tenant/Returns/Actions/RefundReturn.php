<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Exceptions\DomainException;
use App\Shared\Money\Money;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Exceptions\RefundExceedsRefundableException;
use App\Tenant\Returns\Enums\ReturnRefundStatus;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnRefundModuleHeldException;
use App\Tenant\Returns\Exceptions\ReturnTransitionNotAllowedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnRefundAttempts;
use Illuminate\Support\Facades\DB;

/**
 * Staff refunding an inspected return (or an approved one whose buyer keeps the items) through Payments: every unit that came back, at what the buyer paid for it, with shipping if asked (by default when the store was at fault: damaged or the wrong item), less anything kept back with a reason. Payments decides where the money comes from and when it went back; the return completes once it did.
 *
 * Never refunds twice. The attempt is saved, under the return's lock, before
 * Payments is asked, and Payments is asked outside any transaction (it calls
 * gateways after its own commit) with the attempt's key, which it honours.
 * Asking again looks the last attempt up first: one Payments never answered
 * is asked again under the same key with the same request; one still
 * pending, in staff review, or partly refunded is left to Payments and
 * staff; a new attempt is made only once the last definitely got nothing
 * back (Payments refused it, or every part of its refund failed).
 */
final readonly class RefundReturn
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private ReturnRefundAttempts $attempts,
        private RefundOrder $refundOrder,
    ) {}

    /**
     * @param  bool|null  $shipping  Whether to give back shipping too; null for when the store was at fault.
     *
     * @throws ReturnTransitionNotAllowedException When it is neither inspected nor approved with the buyer keeping the items.
     * @throws ReturnRefundModuleHeldException
     * @throws DomainException What Payments refused it with; the refusal is saved on the attempt.
     */
    public function handle(OrderReturn $return, StaffMember $actor, ?bool $shipping = null, ?Money $withheld = null, ?string $withheldReason = null): ReturnRefund
    {
        $by = AccountReference::to($actor);
        $attempt = DB::transaction(fn (): ReturnRefund => $this->attemptFor($return, $by, $shipping, $withheld, $withheldReason));

        if ($attempt->status !== ReturnRefundStatus::Started) {
            return $attempt;
        }

        $order = Order::query()->whereKey($return->order_id)->firstOrFail();

        try {
            $this->refundOrder->handle($actor, $order, $this->requestFor($return, $order, $attempt));
        } catch (RefundExceedsRefundableException $exception) {
            $this->refuse($return, $attempt, $this->holdsModuleMoney($order) ? new ReturnRefundModuleHeldException : $exception);
        } catch (DomainException $exception) {
            $this->refuse($return, $attempt, $exception);
        }

        return DB::transaction(function () use ($return, $attempt, $by): ReturnRefund {
            $this->attempts->settle($this->returnProgress->lock($return), $by);

            return $attempt->refresh();
        });
    }

    /**
     * The attempt to ask Payments about: the last one, unless it definitely got nothing back, else a new one saved now.
     *
     * @throws ReturnTransitionNotAllowedException
     */
    private function attemptFor(OrderReturn $return, AccountReference $by, ?bool $shipping, ?Money $withheld, ?string $withheldReason): ReturnRefund
    {
        $locked = $this->returnProgress->lock($return);
        $this->attempts->settle($locked, $by);
        $last = $locked->refunds()->reorder('attempt', 'desc')->first();

        if ($last !== null && ! $last->status->allowsNewAttempt()) {
            return $last;
        }

        if ($locked->status !== ReturnStatus::Inspected && ! ($locked->status === ReturnStatus::Approved && $locked->keep_items)) {
            throw new ReturnTransitionNotAllowedException;
        }

        $number = ($last->attempt ?? 0) + 1;

        return tap((new ReturnRefund)->forceFill([
            'return_id' => $locked->id,
            'attempt' => $number,
            'idempotency_key' => ReturnRefund::keyFor($locked, $number),
            'status' => ReturnRefundStatus::Started,
            'shipping' => $shipping ?? $locked->reason->isStoresFault(),
            'withheld_amount' => $withheld?->minorAmount() ?? 0,
            'withheld_reason' => $withheld === null ? null : $withheldReason,
            'requested_by_type' => $by->type,
            'requested_by_id' => $by->publicId,
        ]))->save();
    }

    private function requestFor(OrderReturn $return, Order $order, ReturnRefund $attempt): RefundRequest
    {
        $lines = $return->lines()->with('orderLine')->get()->mapWithKeys(static fn (ReturnLine $line): array => [$line->orderLine->public_id => $line->quantity])->all();

        return new RefundRequest(
            lines: $lines,
            shipping: $attempt->shipping,
            reason: 'Return '.$return->displayNumber(),
            idempotencyKey: $attempt->idempotency_key,
            withheld: $attempt->withheld_amount === 0 ? null : Money::ofMinor($attempt->withheld_amount, $order->currency),
            withheldReason: $attempt->withheld_reason,
        );
    }

    /**
     * Saves why the attempt was refused (the return stays where it was), then raises it.
     */
    private function refuse(OrderReturn $return, ReturnRefund $attempt, DomainException $exception): never
    {
        DB::transaction(function () use ($return, $attempt, $exception): void {
            $this->returnProgress->lock($return);
            $attempt->refresh()->forceFill(['status' => ReturnRefundStatus::Refused, 'refusal_code' => $exception->errorCode()])->save();
        });

        throw $exception;
    }

    /**
     * Whether a module's payment method (such as a gift card) holds money paid for the order, which only it could give back.
     */
    private function holdsModuleMoney(Order $order): bool
    {
        return CheckoutPaymentHold::query()->where('order_id', $order->id)->where('status', PaymentHoldStatus::Committed->value)->where('amount_amount', '>', 0)->exists();
    }
}
