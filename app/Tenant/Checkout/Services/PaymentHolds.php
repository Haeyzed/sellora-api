<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Services;

use App\Shared\Exceptions\DomainException;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Events\CommittedPaymentRefundFailed;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RecalculateOrderPayments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Takes what payment methods held for an order at placement through the rest of its life (section 13): committed once the order is paid, released once it expires or is cancelled unpaid, and refunded, by the method giving it back, once it is cancelled after it was paid. Each hold once, under its row lock, so running again (a retried job, both events) does nothing more.
 */
final readonly class PaymentHolds
{
    public function __construct(
        private CheckoutExtensions $checkoutExtensions,
        private RecalculateOrderPayments $recalculateOrderPayments,
    ) {}

    /**
     * Makes every hold still held final: the order is paid.
     */
    public function commit(Order $order): void
    {
        $this->settle($order, PaymentHoldStatus::Committed, static function (CheckoutPaymentMethod $method, CheckoutPaymentHold $hold): void {
            $method->commit($hold);
        });
    }

    /**
     * Gives back every hold still held: the order expired or was cancelled before it was paid.
     */
    public function release(Order $order): void
    {
        $this->settle($order, PaymentHoldStatus::Released, static function (CheckoutPaymentMethod $method, CheckoutPaymentHold $hold): void {
            $method->release($hold);
        });
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
        $givenBack = 0;

        foreach ($holds as $hold) {
            $givenBack += $this->refundOne($hold) ? 1 : 0;
        }

        if ($givenBack > 0) {
            $this->recalculateOrderPayments->handle($order);
        }

        return $givenBack;
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
     * @param  callable(CheckoutPaymentMethod, CheckoutPaymentHold): void  $step
     */
    private function settle(Order $order, PaymentHoldStatus $to, callable $step): void
    {
        $ids = CheckoutPaymentHold::query()->where('order_id', $order->id)->where('status', PaymentHoldStatus::Held->value)->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $to, $step): void {
                $hold = CheckoutPaymentHold::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($hold->status !== PaymentHoldStatus::Held) {
                    return;
                }

                $step($this->checkoutExtensions->paymentMethodFor($hold->method) ?? throw new LogicException("No payment method handles \"{$hold->method}\" any more."), $hold);

                $hold->forceFill([
                    'status' => $to,
                    $to === PaymentHoldStatus::Committed ? 'committed_at' : 'released_at' => CarbonImmutable::now(),
                ])->save();
            });
        }
    }
}
