<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreClosedNotification;
use App\Shared\Auth\AccountReference;
use App\Shared\Features\Features;
use App\Shared\Tenancy\Contracts\StoreSessions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Support\ActivityLogger;

/**
 * Closes a store, by its owner or a platform admin: it stops serving requests, everyone is signed out, and its data is kept until the purge date.
 *
 * The status before closing is recorded, so a restore returns the store to
 * it: closing and restoring a suspended store never lifts the suspension.
 * The owner is emailed the purge date and told to export their data first.
 */
final readonly class CloseStore
{
    public function __construct(
        private StoreSessions $storeSessions,
        private Features $features,
    ) {}

    /**
     * @param  PlatformAdmin|null  $platformAdmin  The platform admin closing it, or null when the owner does.
     *
     * @throws StoreStatusConflictException When the store isn't active, suspended or failed to set up.
     */
    public function handle(Tenant $store, AccountReference $closedBy, ?string $reason, ?PlatformAdmin $platformAdmin = null): Tenant
    {
        $store = Tenant::query()->getConnection()->transaction(static function () use ($store, $closedBy, $reason, $platformAdmin): Tenant {
            $locked = Tenant::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, TenantStatus::closable(), true)) {
                throw new StoreStatusConflictException;
            }

            $now = CarbonImmutable::now();
            $locked->forceFill([
                'status_before_closing' => $locked->status,
                'status' => TenantStatus::Closed,
                'closed_at' => $now,
                'closure_reason' => $reason,
                'closed_by_type' => $closedBy->type,
                'closed_by_id' => $closedBy->publicId,
                'purge_after' => $now->addDays(config()->integer('tenancy.closed_store_retention_days')),
                'purge_reminder_sent_at' => null,
            ])->save();

            // Never the owner as causer: they live in the store's database, so the central log keeps them in closed_by instead.
            activity('stores')
                ->causedBy($platformAdmin)
                ->when($platformAdmin === null, static fn (ActivityLogger $activity): ActivityLogger => $activity->causedByAnonymous())
                ->performedOn($locked)
                ->event('store_closed')
                ->withProperties(['reason' => $reason, 'closed_by' => ['type' => $closedBy->type, 'id' => $closedBy->publicId], 'purge_after' => $locked->purge_after?->toIso8601String()])
                ->log("Closed the store {$locked->name}");

            Notification::route('mail', $locked->owner_email)->notify(StoreClosedNotification::forStore($locked));

            return $locked;
        });

        $this->signEveryoneOut($store);
        $this->features->forget($store->id);

        return $store;
    }

    /**
     * After the store is marked closed, so nobody can sign in again in between. A store that never opened has nobody to sign out.
     */
    private function signEveryoneOut(Tenant $store): void
    {
        if ($store->status_before_closing?->servesRequests() !== true) {
            return;
        }

        $store->run(fn () => $this->storeSessions->endAll());
    }
}
