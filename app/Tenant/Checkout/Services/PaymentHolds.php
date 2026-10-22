<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Services;

use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Contracts\CheckoutPaymentMethod;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Takes what payment methods held for an order at placement through the rest of its life (section 13): committed once the order is paid, released once it expires or is cancelled unpaid. Each hold once, under its row lock, so running again (a retried job, both events) does nothing more.
 */
final readonly class PaymentHolds
{
    public function __construct(private CheckoutExtensions $checkoutExtensions) {}

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
     * Gives back every hold still held: the order expired or was cancelled before it was paid. What was committed stays: money taken goes back only through a refund.
     */
    public function release(Order $order): void
    {
        $this->settle($order, PaymentHoldStatus::Released, static function (CheckoutPaymentMethod $method, CheckoutPaymentHold $hold): void {
            $method->release($hold);
        });
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
