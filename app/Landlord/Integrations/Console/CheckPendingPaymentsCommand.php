<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Console;

use App\Landlord\Integrations\CentralWebhookEndpoints;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StoreCommittedPaymentRefunds;
use App\Shared\Tenancy\Contracts\StorePaymentChecks;
use App\Shared\Tenancy\Contracts\StoreUnsettledPaymentHolds;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The hourly payments sweep in every store with a database: queues a check with the gateway for payments still waiting on it that haven't been asked about for a while, alerts staff about cash on delivery not recorded after delivery and about refunds pending at the gateway for days, commits or releases what payment methods still hold for paid or cancelled orders when settling it failed, asks payment methods again to give back what they took for cancelled orders when that failed, forgets gateway keys kept only for money in motion once it has settled, and deletes webhook endpoints the store's connections no longer use.
 *
 * The safety net behind each payment's own schedule of checks. Suspended and
 * closed stores are swept too: payments already under way are always looked
 * after. One store failing never stops the others.
 */
final class CheckPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:check-pending';

    protected $description = 'Queue checks with the gateway for payments still pending in every store, and prune unused webhook endpoints';

    public function handle(StorePaymentChecks $storePaymentChecks, StoreCommittedPaymentRefunds $storeCommittedPaymentRefunds, StoreUnsettledPaymentHolds $storeUnsettledPaymentHolds, CentralWebhookEndpoints $webhookEndpoints): int
    {
        $failures = 0;
        $orphanCutoff = CarbonImmutable::now()->subMinutes(config()->integer('payments.orphaned_endpoints_after_minutes'));

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $tenant) {
            try {
                [$queued, $alerted, $slowRefunds, $settled, $givenBack, $forgotKeys, $inUse] = $tenant->run(static fn (): array => [$storePaymentChecks->queueOverdueChecks(), $storePaymentChecks->alertOverdueCollections(), $storePaymentChecks->alertSlowRefunds(), $storeUnsettledPaymentHolds->settleUnsettledHolds(), $storeCommittedPaymentRefunds->retryFailedRefunds(), $storePaymentChecks->forgetSettledCredentials(), $storePaymentChecks->webhookEndpointsInUse()]);
                $pruned = $webhookEndpoints->pruneUnused((string) $tenant->getTenantKey(), $inUse, $orphanCutoff);
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: {$exception->getMessage()}");
                $failures++;

                continue;
            }

            if ($queued + $alerted + $slowRefunds + $pruned + $settled + $givenBack + $forgotKeys > 0) {
                $this->components->twoColumnDetail("Store {$tenant->id}", "{$queued} checks queued, {$alerted} cash collections overdue, {$slowRefunds} refunds still pending, {$settled} checkout payment holds settled, {$givenBack} checkout payments given back, {$forgotKeys} gateway keys forgotten after settling, {$pruned} unused webhook endpoints deleted");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
