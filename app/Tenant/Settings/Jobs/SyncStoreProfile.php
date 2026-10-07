<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Jobs;

use App\Shared\Tenancy\Contracts\StoreProfile;
use App\Tenant\Settings\Models\StoreSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings the platform's copy of the store's name, country, currency, timezone and language up to date with its settings.
 *
 * Dispatched in the store's context once a change has committed (section 6).
 * It reads the settings as they are when it runs, not as they were when it
 * was queued, so it is safe to run twice or out of order, and it retries
 * until the platform accepts it.
 */
final class SyncStoreProfile implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    /**
     * Keeps retrying for a week; a platform outage longer than that needs a person anyway.
     */
    public function retryUntil(): DateTimeInterface
    {
        return CarbonImmutable::now()->addWeek();
    }

    public function handle(StoreProfile $storeProfile): void
    {
        $settings = StoreSettings::query()->find(1);

        if ($settings === null) {
            return;
        }

        $storeProfile->update($settings->profileDetails());
    }
}
