<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Money;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Events\PaymentOverpaid;
use App\Tenant\Payments\Events\PaymentSucceeded;
use App\Tenant\Payments\Exceptions\PaymentNotAwaitingReceiptException;
use App\Tenant\Payments\Exceptions\PaymentReceiptInvalidException;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\OrderPayments;
use App\Tenant\Payments\Services\PaymentActivity;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff recording money paid by hand: a bank transfer that arrived, or cash collected on delivery (payments.confirm). What arrived counts toward the order, under the order's lock taken before the payment's.
 *
 * Less than expected: what arrived counts, and a new pending payment of the
 * same method expects the rest, by the order's same deadline (if it is still
 * waiting for payment). More than expected: it all counts, the order is
 * paid, and staff are told the difference is owed back. Money recorded for a
 * payment cancelled with its order still counts, and the order owes it back.
 */
final readonly class RecordManualPayment
{
    public function __construct(
        private OrderPayments $orderPayments,
        private PaymentEventLog $paymentEventLog,
        private PaymentActivity $paymentActivity,
    ) {}

    /**
     * @param  string|null  $reference  The bank's or the store's own reference for the money, if any.
     * @param  PaymentMethod|null  $method  The method staff say it was paid by (a transfer received, cash collected); null for either.
     *
     * @throws PaymentNotAwaitingReceiptException When it isn't paid by hand (or by that method), or isn't pending or cancelled.
     * @throws PaymentReceiptInvalidException When what arrived isn't a positive amount in the payment's currency.
     */
    public function handle(StaffMember $actor, Payment $payment, Money $received, ?string $reference = null, ?PaymentMethod $method = null): Payment
    {
        $recorded = $this->record(AccountReference::to($actor), $payment, $received, $reference, $method);
        $this->paymentActivity->record($actor, $recorded, 'payment_recorded', 'Recorded a payment received by '.str_replace('_', ' ', $recorded->method->value));

        return $recorded;
    }

    /**
     * Records what arrived for whoever took it, such as a driver collecting cash on delivery (Step 17), without an activity entry: the caller writes its own. Joins the caller's transaction when there is one.
     *
     * @throws PaymentNotAwaitingReceiptException
     * @throws PaymentReceiptInvalidException
     */
    public function record(AccountReference $actor, Payment $payment, Money $received, ?string $reference = null, ?PaymentMethod $method = null): Payment
    {
        if (! $received->isPositive() || ! $received->hasSameCurrencyAs($payment->amount)) {
            throw new PaymentReceiptInvalidException;
        }

        return DB::transaction(function () use ($actor, $payment, $received, $reference, $method): Payment {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $isThatMethod = $method === null || $locked->method === $method;

            if (! $locked->method->isManual() || ! $isThatMethod || ! in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Cancelled], true)) {
                throw new PaymentNotAwaitingReceiptException;
            }

            $wasExpected = $locked->status === PaymentStatus::Pending;
            $this->markReceived($locked, $received, $actor, $reference);

            if ($received->isLessThan($locked->amount) && $wasExpected && $order->awaitsPayment()) {
                $this->expectTheRest($locked, $locked->amount->subtract($received));
            }

            if ($locked->amount->isLessThan($received)) {
                $this->reportOverpayment($locked, $received->subtract($locked->amount));
            }

            PaymentSucceeded::dispatch($locked, $order, $this->orderPayments->totals($order));

            return $locked;
        });
    }

    private function markReceived(Payment $locked, Money $received, AccountReference $actor, ?string $reference): void
    {
        $locked->forceFill([
            'status' => PaymentStatus::Succeeded,
            'received' => $received,
            'succeeded_at' => CarbonImmutable::now(),
            'confirmed_by_type' => $actor->type,
            'confirmed_by_id' => $actor->publicId,
        ])->save();
        $this->paymentEventLog->record($locked, PaymentEventType::Succeeded, array_filter(['amount' => $received->minorAmount(), 'reference' => $reference], static fn (int|string|null $value): bool => $value !== null), $actor);
    }

    /**
     * A new pending payment of the same method for what is still missing.
     */
    private function expectTheRest(Payment $short, Money $rest): void
    {
        $remainder = new Payment;
        $remainder->forceFill([
            'order_id' => $short->order_id,
            'method' => $short->method,
            'is_test' => false,
            'status' => PaymentStatus::Pending,
            'amount' => $rest,
            'reference' => 'pay_'.Str::lower((string) Str::ulid()),
        ])->save();
        $this->paymentEventLog->record($remainder, PaymentEventType::Started, ['method' => $short->method->value, 'rest_of' => $short->public_id]);
    }

    private function reportOverpayment(Payment $locked, Money $excess): void
    {
        $this->paymentEventLog->record($locked, PaymentEventType::Overpaid, ['excess' => $excess->minorAmount()]);
        PaymentOverpaid::dispatch($locked, $excess);
    }
}
