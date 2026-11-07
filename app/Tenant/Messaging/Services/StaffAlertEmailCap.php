<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Services;

use App\Tenant\Messaging\Enums\StaffAlertKind;
use App\Tenant\Messaging\Models\StaffAlert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * How many alerts a store emails its staff an hour (config messaging.staff_alerts.emails_per_hour), so a burst (a sale selling out a hundred variants) never floods their inboxes. The rest are still in their alert list.
 *
 * Money alerts (payments, refunds, holds, disputes, overpayments, cash
 * collection, exchange settlement) are never held back and never count
 * towards the cap: they are always emailed. Alerts are counted under a lock,
 * so two raised at the same moment can't both take the last place.
 */
final readonly class StaffAlertEmailCap
{
    /**
     * Marks the alert as emailed and returns true, or returns false when it is held back by the cap (and leaves it unmarked).
     */
    public function claimEmail(StaffAlert $alert): bool
    {
        if ($alert->kind->isMoney()) {
            $alert->forceFill(['emailed_at' => CarbonImmutable::now()])->save();

            return true;
        }

        return DB::transaction(static function () use ($alert): bool {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['staff-alert-email-cap']);
            $emailedThisHour = StaffAlert::query()
                ->whereIn('kind', array_map(static fn (StaffAlertKind $kind): string => $kind->value, StaffAlertKind::cappedKinds()))
                ->where('emailed_at', '>', CarbonImmutable::now()->subHour())
                ->count();

            if ($emailedThisHour >= config()->integer('messaging.staff_alerts.emails_per_hour')) {
                return false;
            }

            $alert->forceFill(['emailed_at' => CarbonImmutable::now()])->save();

            return true;
        });
    }
}
