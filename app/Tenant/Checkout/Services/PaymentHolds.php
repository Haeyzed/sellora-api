<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Services;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Events\CommittedPaymentRefundFailed;
use App\Tenant\Checkout\Events\PaymentHoldKeepsFailing;
use App\Tenant\Checkout\Exceptions\OrderPaymentsNotRecordedException;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RecalculateOrderPayments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Takes what payment methods held for an order at placement through the rest of its life (section 13): committed once the order is paid, released once it expires or is cancelled unpaid, and refunded, by the method giving it back, once it is cancelled after it was paid. Each hold once, under its row lock, so running again (a retried job, both events, the hourly retry) does nothing more.
 *
 * Each hold is settled on its own: a method that throws, or is no longer
 * registered, never stops the others. Its failure is recorded on its hold,
 * logged, and tried again by the hourly payments sweep until it settles;
 * staff are alerted once when it has failed checkout.hold_settle_alert_after
 * tries in a row. Recording the order's payments once holds were released or
 * given back is isolated the same way, after them.
 */
final readonly class PaymentHolds
{
    public function __construct(
        private CheckoutExtensions $checkoutExtensions,
        private RecalculateOrderPayments $recalculateOrderPayments,
    ) {}

    /**
     * Makes every hold still held final: the order is paid.
     *
     * @return int How many were committed.
     */
    public function commit(Order $order): int
    {
        return count($this->settle($order, PaymentHoldStatus::Committed, static function (CheckoutPaymentMethod $method, CheckoutPaymentHold $hold): void {
            $method->commit($hold);
        }));
    }

    /**
     * Gives back every hold still held: the order expired or was cancelled before it was paid. When it had counted as paid (its commit failed before it was cancelled), the order's payments are recorded again through Payments, so what was released stops counting; that is isolated from the release, see recordOrderPayments().
     *
     * @return int How many were released.
     */
    public function release(Order $order): int
    {
        $released = $this->settle($order, PaymentHoldStatus::Released, static function (CheckoutPaymentMethod $method, CheckoutPaymentHold $hold): void {
            $method->release($hold);
        });

        if ($released !== [] && $order->refresh()->payment_status->holdsMoney()) {
            $this->recordOrderPayments($order, $released);
        }

        return count($released);
    }

    /**
     * Records again the payments of an order where that failed after its holds settled, as the hourly sweep asks.
     *
     * @return bool Whether they are recorded now.
     */
    public function retryRecordingOrderPayments(Order $order): bool
    {
        $holdIds = [];

        foreach (CheckoutPaymentHold::query()->where('order_id', $order->id)->whereNotNull('order_update_failed_at')->orderBy('id')->get(['id']) as $hold) {
            $holdIds[] = $hold->id;
        }

        return $holdIds === [] || $this->recordOrderPayments($order, $holdIds);
    }

    /**
     * Asks each method that committed money for a cancelled order to give it back, then moves the order's payment status through Payments, so what was given back stops counting as paid and the refund it owes can clear.
     *
     * Core's own methods hold nothing (their money is refunded through
     * Payments), so only holds that took an amount are asked. Each method is
     * asked outside any transaction, as it may call out; one that can't give
     * back, can't be reached or refuses leaves its hold committed and the
     * order owing the refund for staff, records why on the hold, and is asked
     * again by the hourly sweep.
     *
     * @return int How many holds were given back.
     */
    public function refund(Order $order): int
    {
        $holds = CheckoutPaymentHold::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentHoldStatus::Committed->value)
            ->where('amount_amount', '>', 0)
            ->orderBy('id')
            ->get();
        $givenBack = [];

        foreach ($holds as $hold) {
            if ($this->refundOne($hold)) {
                $givenBack[] = $hold->id;
            }
        }

        if ($givenBack !== []) {
            $this->recordOrderPayments($order, $givenBack);
        }

        return count($givenBack);
    }

    /**
     * Records the order's payments through Payments once holds settled or were given back, and anything that triggers, in a transaction of its own (a savepoint inside the caller's).
     *
     * The holds have settled for good (their methods are called only once), so
     * a failure here, a database constraint included, never undoes them and
     * never stops the caller: it is recorded on those holds, logged with public
     * IDs only, the error tracker is told without personal data, and the
     * hourly sweep tries again. Once it works, the failure is cleared.
     *
     * @param  list<int>  $holdIds
     * @return bool Whether they are recorded.
     */
    private function recordOrderPayments(Order $order, array $holdIds): bool
    {
        try {
            DB::transaction(fn (): Order => $this->recalculateOrderPayments->handle($order));
        } catch (Throwable $exception) {
            $this->recordOrderUpdateFailure($order, $holdIds, $exception);

            return false;
        }

        $cleared = CheckoutPaymentHold::query()->whereKey($holdIds)->whereNotNull('order_update_failed_at')->update(['order_update_failed_at' => null, 'order_update_failure' => null, 'updated_at' => CarbonImmutable::now()]);

        if ($cleared > 0) {
            Log::info('An order\'s payments that had failed to record after its payment holds settled are now recorded.', ['order' => $order->public_id]);
        }

        return true;
    }

    /**
     * @param  list<int>  $holdIds
     */
    private function recordOrderUpdateFailure(Order $order, array $holdIds, Throwable $exception): void
    {
        $reason = $exception instanceof DomainException ? $exception->errorCode() : 'order_payments_not_recorded';
        $publicIds = [];
        $methods = [];
        $tries = 0;

        DB::transaction(static function () use ($holdIds, $reason, &$publicIds, &$methods, &$tries): void {
            foreach (CheckoutPaymentHold::query()->whereKey($holdIds)->orderBy('id')->lockForUpdate()->get() as $hold) {
                $hold->forceFill(['order_update_failed_at' => CarbonImmutable::now(), 'order_update_failure' => $reason, 'order_update_failures' => $hold->order_update_failures + 1])->save();
                $publicIds[] = $hold->public_id;
                $methods[] = $hold->method;
                $tries = max($tries, $hold->order_update_failures);
            }
        });

        report(OrderPaymentsNotRecordedException::after($exception, $order->public_id, $publicIds));
        // Only public IDs and the methods' keys: staff find the rest from there.
        Log::warning('Recording an order\'s payments failed after its payment holds settled; it is tried again hourly.', ['order' => $order->public_id, 'methods' => $methods, 'reason' => $reason, 'tries_failed' => $tries]);
    }

    private function refundOne(CheckoutPaymentHold $hold): bool
    {
        $refunds = $this->checkoutExtensions->refundsFor($hold->method);

        if ($refunds === null) {
            $this->recordRefundFailure($hold, 'payment_method_cannot_refund');

            return false;
        }

        try {
            $refunds->refund($hold);
        } catch (Throwable $exception) {
            // What it says, for staff; anything it didn't mean to throw goes to the error tracker too.
            if (! $exception instanceof DomainException) {
                report($exception);
            }

            $this->recordRefundFailure($hold, $exception instanceof DomainException ? $exception->errorCode() : 'payment_method_refund_failed');

            return false;
        }

        return DB::transaction(static function () use ($hold): bool {
            $locked = CheckoutPaymentHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentHoldStatus::Committed) {
                return false;
            }

            $locked->forceFill(['status' => PaymentHoldStatus::Refunded, 'refunded_at' => CarbonImmutable::now(), 'refund_failed_at' => null, 'refund_failure' => null])->save();

            return true;
        });
    }

    private function recordRefundFailure(CheckoutPaymentHold $hold, string $reason): void
    {
        $recorded = DB::transaction(static function () use ($hold, $reason): bool {
            $locked = CheckoutPaymentHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentHoldStatus::Committed) {
                return false;
            }

            $locked->forceFill(['refund_failed_at' => CarbonImmutable::now(), 'refund_failure' => mb_substr($reason, 0, 64)])->save();

            return true;
        });

        if (! $recorded) {
            return;
        }

        // Only public IDs and the method's key: staff find the rest from there.
        Log::warning('A payment method could not give back what it took for a cancelled order; it is tried again hourly, and the order owes the refund until then.', ['order' => $hold->order->public_id, 'method' => $hold->method, 'reason' => $reason]);
        CommittedPaymentRefundFailed::dispatch($hold->refresh());
    }

    /**
     * Settles each hold still held, each in a transaction of its own under its row lock, so one failing never stops the others.
     *
     * @param  callable(CheckoutPaymentMethod, CheckoutPaymentHold): void  $step
     * @return list<int> The IDs of those settled now.
     */
    private function settle(Order $order, PaymentHoldStatus $to, callable $step): array
    {
        $ids = CheckoutPaymentHold::query()->where('order_id', $order->id)->where('status', PaymentHoldStatus::Held->value)->orderBy('id')->pluck('id');
        $settled = [];

        foreach ($ids as $id) {
            try {
                $outcome = DB::transaction(fn (): bool|string => $this->settleOne($id, $to, $step));
            } catch (Throwable $exception) {
                // What it says, for staff; anything it didn't mean to throw goes to the error tracker too.
                if (! $exception instanceof DomainException) {
                    report($exception);
                }

                $outcome = $exception instanceof DomainException ? $exception->errorCode() : 'payment_method_settle_failed';
            }

            if (is_string($outcome)) {
                $this->recordSettleFailure($id, $to, $outcome);
            }

            if ($outcome === true) {
                $settled[] = (int) $id;
            }
        }

        return $settled;
    }

    /**
     * @param  callable(CheckoutPaymentMethod, CheckoutPaymentHold): void  $step
     * @return bool|string Whether it was settled now (false when it already was), or why it couldn't be.
     */
    private function settleOne(int $id, PaymentHoldStatus $to, callable $step): bool|string
    {
        $hold = CheckoutPaymentHold::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        if ($hold->status !== PaymentHoldStatus::Held) {
            return false;
        }

        $method = $this->checkoutExtensions->paymentMethodFor($hold->method);

        if ($method === null) {
            return 'payment_method_not_registered';
        }

        $step($method, $hold);
        $recovered = $hold->settle_failed_at !== null;

        $hold->forceFill([
            'status' => $to,
            $to === PaymentHoldStatus::Committed ? 'committed_at' : 'released_at' => CarbonImmutable::now(),
            'settle_failed_at' => null,
            'settle_failure' => null,
            ...($recovered ? ['settle_recovered_at' => CarbonImmutable::now()] : []),
        ])->save();

        if ($recovered) {
            Log::info('A payment hold that had failed to settle is now settled.', ['order' => $hold->order->public_id, 'method' => $hold->method, 'status' => $to->value, 'tries_failed' => $hold->settle_failures]);
        }

        return true;
    }

    /**
     * Records why a hold couldn't be committed or released, while it is still held, and alerts staff once when it has failed checkout.hold_settle_alert_after tries in a row.
     */
    private function recordSettleFailure(int $id, PaymentHoldStatus $to, string $reason): void
    {
        $recorded = DB::transaction(static function () use ($id, $reason): ?array {
            $locked = CheckoutPaymentHold::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            // Settled meanwhile, by another try that worked.
            if ($locked->status !== PaymentHoldStatus::Held) {
                return null;
            }

            $failures = $locked->settle_failures + 1;
            $alert = $locked->settle_alerted_at === null && $failures >= config()->integer('checkout.hold_settle_alert_after');

            $locked->forceFill([
                'settle_failed_at' => CarbonImmutable::now(),
                'settle_failure' => mb_substr($reason, 0, 64),
                'settle_failures' => $failures,
                ...($alert ? ['settle_alerted_at' => CarbonImmutable::now()] : []),
            ])->save();

            return [$locked, $alert];
        });

        if ($recorded === null) {
            return;
        }

        [$hold, $alert] = $recorded;
        // Only public IDs and the method's key: staff find the rest from there.
        Log::warning('A payment method could not settle what it holds for an order; it is tried again hourly.', ['order' => $hold->order->public_id, 'method' => $hold->method, 'to' => $to->value, 'reason' => $reason, 'tries_failed' => $hold->settle_failures]);

        if ($alert) {
            PaymentHoldKeepsFailing::dispatch($hold);
        }
    }
}
