<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Data\PlatformAlertData;
use App\Landlord\Identity\PlatformAlertNotification;
use App\Landlord\Identity\Services\PlatformAlertRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Emails a platform alert to the platform admins who can act on it, each in their saved language.
 *
 * A kind that may come again day after day (a scheduled check that keeps
 * failing) is sent at most once a day per store; claiming the day is atomic,
 * so two runs at the same moment send it once.
 */
final readonly class AlertPlatformAdmins
{
    public function __construct(private PlatformAlertRecipients $recipients) {}

    /**
     * @return bool Whether it was sent; false when it was sent already today.
     */
    public function handle(PlatformAlertData $alert): bool
    {
        if ($alert->kind->repeatsDaily() && ! $this->claimToday($alert)) {
            return false;
        }

        foreach ($this->recipients->for($alert->kind) as $platformAdmin) {
            $platformAdmin->notify(new PlatformAlertNotification($alert->kind, $alert->details));
        }

        return true;
    }

    private function claimToday(PlatformAlertData $alert): bool
    {
        $today = CarbonImmutable::now()->toDateString();
        $key = implode(':', ['platform-alert', $alert->kind->value, $alert->storeId ?? 'platform', $today]);

        return Cache::add($key, true, CarbonImmutable::now()->addDays(2));
    }
}
