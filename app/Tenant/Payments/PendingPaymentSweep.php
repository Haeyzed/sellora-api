<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Tenancy\Contracts\StorePaymentChecks;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Payments\Actions\FindManualPaymentMethods;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Enums\RefundStatus;
use App\Tenant\Payments\Events\CashCollectionOverdue;
use App\Tenant\Payments\Events\RefundStillPending;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use App\Tenant\Payments\Services\GatewayCredentialsKeeper;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The current store's part of the hourly payments sweep, behind each payment's own schedule of checks: whatever slipped through (a check that never ran, a webhook whose handling was lost) is picked up here.
 *
 * Queues a one-off check for every gateway payment pending without a check
 * for config payments.checks.sweep_after_minutes, and every unresolved one
 * not asked about for recheck_unresolved_after_hours; handles again any
 * recorded webhook still unhandled after as long; and asks again about every
 * gateway refund still pending that long (once a day, and staff alerted once,
 * after refund_daily_after_days). Never gated by the plan or the store's
 * status. Also alerts staff about cash on delivery not recorded as collected
 * a while after delivery, and forgets the keys gateways kept only for money
 * in motion once it has settled.
 */
final readonly class PendingPaymentSweep implements StorePaymentChecks
{
    public function __construct(
        private FindManualPaymentMethods $findManualPaymentMethods,
        private PaymentEventLog $paymentEventLog,
        private GatewayCredentialsKeeper $gatewayCredentialsKeeper,
    ) {}

    public function queueOverdueChecks(): int
    {
        $limit = config()->integer('payments.checks.sweep_per_run');
        $paymentIds = $this->overduePayments()->orderBy('id')->limit($limit)->pluck('id');
        $webhookIds = PaymentWebhookEvent::query()
            ->whereNull('processed_at')
            ->where('received_at', '<', $this->pendingCutoff())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($paymentIds as $paymentId) {
            CheckPaymentWithGateway::dispatch($paymentId);
        }

        foreach ($webhookIds as $webhookId) {
            ProcessPaymentWebhook::dispatch($webhookId);
        }

        $refundIds = $this->overdueRefunds()->orderBy('id')->limit($limit)->pluck('id');

        foreach ($refundIds as $refundId) {
            CheckRefundWithGateway::dispatch($refundId);
        }

        return $paymentIds->count() + $webhookIds->count() + $refundIds->count();
    }

    public function alertOverdueCollections(): int
    {
        $alertHours = $this->findManualPaymentMethods->find(PaymentMethod::CashOnDelivery)->collectionAlertHours
            ?? config()->integer('payments.manual_methods.cash_on_delivery.collection_alert_hours');
        $deliveredBefore = CarbonImmutable::now()->subHours($alertHours);
        $overdue = Payment::query()
            ->where('method', PaymentMethod::CashOnDelivery->value)
            ->where('status', PaymentStatus::Pending->value)
            ->whereNull('collection_alerted_at')
            ->whereHas('order.shipments', static fn (Builder $shipments) => $shipments->where('status', ShipmentStatus::Delivered->value)->where('delivered_at', '<', $deliveredBefore))
            ->orderBy('id')
            ->limit(config()->integer('payments.checks.sweep_per_run'))
            ->get();
        $alerted = 0;

        foreach ($overdue as $payment) {
            $alerted += $this->alertOnce($payment) ? 1 : 0;
        }

        return $alerted;
    }

    public function alertSlowRefunds(): int
    {
        $slow = $this->pendingGatewayRefunds()
            ->where('created_at', '<', $this->dailyRefundChecksFrom())
            ->whereNull('slow_alerted_at')
            ->orderBy('id')
            ->limit(config()->integer('payments.checks.sweep_per_run'))
            ->get();
        $alerted = 0;

        foreach ($slow as $paymentRefund) {
            $alerted += $this->alertSlowRefundOnce($paymentRefund) ? 1 : 0;
        }

        return $alerted;
    }

    public function forgetSettledCredentials(): int
    {
        return $this->gatewayCredentialsKeeper->forgetSettled();
    }

    public function webhookEndpointsInUse(): array
    {
        return array_values(PaymentGatewayConnection::query()->orderBy('id')->get()->flatMap(static fn (PaymentGatewayConnection $connection): array => $connection->endpointIds())->all());
    }

    /**
     * @return Builder<Payment>
     */
    private function overduePayments(): Builder
    {
        $pendingCutoff = $this->pendingCutoff();
        $unresolvedCutoff = CarbonImmutable::now()->subHours(config()->integer('payments.checks.recheck_unresolved_after_hours'));

        return Payment::query()
            ->where('method', PaymentMethod::Gateway->value)
            ->where(static fn (Builder $overdue) => $overdue
                ->where(static fn (Builder $pending) => $pending
                    ->where('status', PaymentStatus::Pending->value)
                    ->where(static fn (Builder $quiet) => $quiet
                        ->where('last_checked_at', '<', $pendingCutoff)
                        ->orWhere(static fn (Builder $never) => $never->whereNull('last_checked_at')->where('created_at', '<', $pendingCutoff))))
                ->orWhere(static fn (Builder $unresolved) => $unresolved
                    ->where('status', PaymentStatus::Unresolved->value)
                    ->where('last_checked_at', '<', $unresolvedCutoff)));
    }

    /**
     * Refunds still on their way back through a gateway, not asked about for a while: a day, once they are older than refund_daily_after_days.
     *
     * @return Builder<PaymentRefund>
     */
    private function overdueRefunds(): Builder
    {
        $cutoff = $this->pendingCutoff();
        $dailyFrom = $this->dailyRefundChecksFrom();
        $dailyCutoff = CarbonImmutable::now()->subDay();

        return $this->pendingGatewayRefunds()->where(static fn (Builder $due) => $due
            ->where(static fn (Builder $recent) => $recent
                ->where('created_at', '>=', $dailyFrom)
                ->where(static fn (Builder $quiet) => $quiet
                    ->where('last_checked_at', '<', $cutoff)
                    ->orWhere(static fn (Builder $never) => $never->whereNull('last_checked_at')->where('created_at', '<', $cutoff))))
            ->orWhere(static fn (Builder $old) => $old
                ->where('created_at', '<', $dailyFrom)
                ->where(static fn (Builder $quiet) => $quiet->whereNull('last_checked_at')->orWhere('last_checked_at', '<', $dailyCutoff))));
    }

    /**
     * @return Builder<PaymentRefund>
     */
    private function pendingGatewayRefunds(): Builder
    {
        return PaymentRefund::query()
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('recorded_by_type');
    }

    private function dailyRefundChecksFrom(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(config()->integer('payments.checks.refund_daily_after_days'));
    }

    /**
     * Marked in one conditional update, so two sweeps at once alert once.
     */
    private function alertSlowRefundOnce(PaymentRefund $paymentRefund): bool
    {
        $marked = PaymentRefund::query()
            ->whereKey($paymentRefund->id)
            ->where('status', RefundStatus::Pending->value)
            ->whereNull('slow_alerted_at')
            ->update(['slow_alerted_at' => CarbonImmutable::now()]);

        if ($marked !== 1) {
            return false;
        }

        $this->paymentEventLog->record($paymentRefund->payment, PaymentEventType::RefundStillPending, ['refund' => $paymentRefund->public_id]);
        // Only public IDs: staff find the rest from there.
        Log::warning('A refund is still pending at the gateway days after it was sent; it is asked about daily now.', ['refund' => $paymentRefund->public_id]);
        RefundStillPending::dispatch($paymentRefund->refresh());

        return true;
    }

    /**
     * Marked in one conditional update, so two sweeps at once alert once.
     */
    private function alertOnce(Payment $payment): bool
    {
        $marked = Payment::query()
            ->whereKey($payment->id)
            ->where('status', PaymentStatus::Pending->value)
            ->whereNull('collection_alerted_at')
            ->update(['collection_alerted_at' => CarbonImmutable::now()]);

        if ($marked !== 1) {
            return false;
        }

        $this->paymentEventLog->record($payment, PaymentEventType::CollectionOverdue);
        // Only the public ID: staff find the rest from there.
        Log::warning('Cash on delivery has not been recorded as collected a while after delivery.', ['payment' => $payment->public_id]);
        CashCollectionOverdue::dispatch($payment->refresh());

        return true;
    }

    private function pendingCutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes(config()->integer('payments.checks.sweep_after_minutes'));
    }
}
