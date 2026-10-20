<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Listeners;

use App\Tenant\Orders\Events\OrderCancelled;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A cancelled order no longer expects money paid by hand: its pending bank transfer or cash on delivery payments are cancelled. Money recorded for one afterwards still counts, and the order owes it back.
 *
 * A gateway payment under way is left alone: the gateway is still asked
 * about it, so money that went through after all is caught and owed back.
 */
final readonly class CancelManualPaymentsWhenOrderCancelled
{
    public function __construct(private PaymentEventLog $paymentEventLog) {}

    public function handle(OrderCancelled $event): void
    {
        DB::transaction(function () use ($event): void {
            Order::query()->whereKey($event->order->id)->lockForUpdate()->firstOrFail();
            $pending = Payment::query()
                ->where('order_id', $event->order->id)
                ->whereIn('method', array_map(static fn (PaymentMethod $method): string => $method->value, PaymentMethod::manual()))
                ->where('status', PaymentStatus::Pending->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $payment) {
                $payment->forceFill(['status' => PaymentStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now()])->save();
                $this->paymentEventLog->record($payment, PaymentEventType::Cancelled, ['reason' => 'order_cancelled']);
            }
        });
    }
}
