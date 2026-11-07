<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Listeners;

use App\Tenant\Messaging\Actions\RaiseStaffAlert;
use App\Tenant\Messaging\Services\StaffAlertFromEvent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Raises a staff alert when something needs staff attention (StaffAlertFromEvent::EVENTS): a new order, a payment to review, a refund that failed, stock running low, and the rest.
 *
 * Runs only once the change it is about has committed, so a change that is
 * rolled back alerts nobody. An alert that can't be raised is reported to
 * the error tracker, never allowed to break what announced it (a payment
 * webhook, a stock change).
 */
final readonly class AlertStaffWhenAttentionIsNeeded implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private StaffAlertFromEvent $staffAlertFromEvent,
        private RaiseStaffAlert $raiseStaffAlert,
    ) {}

    public function handle(object $event): void
    {
        try {
            $alert = $this->staffAlertFromEvent->for($event);

            if ($alert !== null) {
                $this->raiseStaffAlert->handle($alert);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
