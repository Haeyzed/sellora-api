<?php

declare(strict_types=1);

namespace App\Landlord\Integrations\Console;

use App\Landlord\Integrations\CentralWebhookEndpoints;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Tenancy\Contracts\StorePaymentChecks;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The hourly payments sweep in every store with a database: queues a check with the gateway for payments still waiting on it that haven't been asked about for a while, and deletes webhook endpoints the store's connections no longer use.
 *
 * The safety net behind each payment's own schedule of checks. Suspended and
 * closed stores are swept too: payments already under way are always looked
 * after. One store failing never stops the others.
 */
final class CheckPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:check-pending';

    protected $description = 'Queue checks with the gateway for payments still pending in every store, and prune unused webhook endpoints';

    public function handle(StorePaymentChecks $storePaymentChecks, CentralWebhookEndpoints $webhookEndpoints): int
    {
        $failures = 0;
        $orphanCutoff = CarbonImmutable::now()->subMinutes(config()->integer('payments.orphaned_endpoints_after_minutes'));

        foreach (Tenant::query()->whereNotIn('status', TenantStatus::withoutDatabase())->lazyById(100) as $tenant) {
            try {
                [$queued, $inUse] = $tenant->run(static fn (): array => [$storePaymentChecks->queueOverdueChecks(), $storePaymentChecks->webhookEndpointsInUse()]);
                $pruned = $webhookEndpoints->pruneUnused((string) $tenant->getTenantKey(), $inUse, $orphanCutoff);
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("Store {$tenant->id}: {$exception->getMessage()}");
                $failures++;

                continue;
            }

            if ($queued + $pruned > 0) {
                $this->components->twoColumnDetail("Store {$tenant->id}", "{$queued} checks queued, {$pruned} unused webhook endpoints deleted");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
