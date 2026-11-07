<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Listeners;

use App\Landlord\Identity\Actions\AlertPlatformAdmins;
use App\Landlord\Identity\Services\PlatformAlertFromEvent;
use Throwable;

/**
 * Alerts Sellora's team when something on the platform needs them (PlatformAlertFromEvent::EVENTS): exchange rates not refreshed, a store's setup failing, a scheduled check finding a mismatch.
 *
 * An alert that can't be sent is reported to the error tracker, never allowed
 * to break what announced it (the rate refresh, a scheduled check).
 */
final readonly class AlertPlatformAdminsWhenAttentionIsNeeded
{
    public function __construct(
        private PlatformAlertFromEvent $platformAlertFromEvent,
        private AlertPlatformAdmins $alertPlatformAdmins,
    ) {}

    public function handle(object $event): void
    {
        try {
            $alert = $this->platformAlertFromEvent->for($event);

            if ($alert !== null) {
                $this->alertPlatformAdmins->handle($alert);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
