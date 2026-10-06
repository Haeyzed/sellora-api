<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreClosedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds the owners of closed stores, once, that the store's data will be deleted soon (7 days before, from config).
 *
 * Scheduled daily. Each store is locked while it is reminded, so two runs
 * can't send the same reminder twice.
 */
final class SendPurgeRemindersCommand extends Command
{
    protected $signature = 'stores:send-purge-reminders';

    protected $description = 'Remind the owners of closed stores that their data will be deleted soon';

    public function handle(): int
    {
        $remindBefore = CarbonImmutable::now()->addDays(config()->integer('tenancy.purge_reminder_days'));
        $reminded = 0;

        foreach ($this->storesToRemind($remindBefore)->lazyById(100) as $store) {
            $reminded += $this->remind($store, $remindBefore) ? 1 : 0;
        }

        $this->components->info("Reminded the owners of {$reminded} closed stores.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Tenant>
     */
    private function storesToRemind(CarbonImmutable $remindBefore): Builder
    {
        return Tenant::query()
            ->where('status', TenantStatus::Closed)
            ->whereNull('purge_reminder_sent_at')
            ->where('purge_after', '<=', $remindBefore);
    }

    private function remind(Tenant $store, CarbonImmutable $remindBefore): bool
    {
        return Tenant::query()->getConnection()->transaction(static function () use ($store, $remindBefore): bool {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TenantStatus::Closed || $locked->purge_reminder_sent_at !== null || $locked->purge_after === null || $locked->purge_after->greaterThan($remindBefore)) {
                return false;
            }

            $locked->forceFill(['purge_reminder_sent_at' => CarbonImmutable::now()])->save();
            Notification::route('mail', $locked->owner_email)->notify(StoreClosedNotification::reminderForStore($locked));

            return true;
        });
    }
}
