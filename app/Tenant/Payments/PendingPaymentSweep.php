<?php

declare(strict_types=1);

namespace App\Tenant\Payments;

use App\Shared\Tenancy\Contracts\StorePaymentChecks;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Payments\Actions\FindManualPaymentMethods;
use App\Tenant\Payments\Enums\PaymentEventType;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentStatus;
use App\Tenant\Payments\Events\CashCollectionOverdue;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\ProcessPaymentWebhook;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\Models\PaymentWebhookEvent;
use App\Tenant\Payments\Services\PaymentEventLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * The current store's part of the hourly payments sweep, behind each payment's own schedule of checks: whatever slipped through (a check that never ran, a webhook whose handling was lost) is picked up here.
 *
 * Queues a one-off check for every gateway payment pending without a check
 * for config payments.checks.sweep_after_minutes, and every unresolved one
 * not asked about for recheck_unresolved_after_hours; and handles again any
 * recorded webhook still unhandled after as long. Never gated by the plan or
 * the store's status. Also alerts staff about cash on delivery not recorded
 * as collected a while after delivery.
 */
final readonly class PendingPaymentSweep implements StorePaymentChecks
{
    public function __construct(
        private FindManualPaymentMethods $findManualPaymentMethods,
        private PaymentEventLog $paymentEventLog,
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

        return $paymentIds->count() + $webhookIds->count();
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

    public function webhookEndpointsInUse(): array
    {
        return array_values(PaymentGatewayConnection::query()->whereNotNull('endpoint_id')->orderBy('id')->pluck('endpoint_id')->all());
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
