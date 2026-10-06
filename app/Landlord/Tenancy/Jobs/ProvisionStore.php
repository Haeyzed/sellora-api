<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Jobs;

use App\Landlord\Tenancy\Enums\TenantStatus;
use App\Landlord\Tenancy\Models\StoreRegistration;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\Services\DatabaseServerPlacement;
use App\Landlord\Tenancy\Services\StoreDatabase;
use App\Landlord\Tenancy\StoreReadyNotification;
use App\Shared\Tenancy\Contracts\StoreOwnerAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Sets up a newly registered store: places it on a database server in its region, creates and migrates its database, creates the owner's account, and opens it.
 *
 * Every step is safe to repeat, so a failed attempt is simply retried. Only
 * when every retry has failed is the store marked as failed, for a platform
 * admin to look at. The payload holds only the store's ID, never the
 * owner's password hash.
 */
final class ProvisionStore implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $tenantId) {}

    /**
     * Waits longer before each retry, so a short outage (a server restarting) doesn't use up every try.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(DatabaseServerPlacement $databaseServerPlacement, StoreDatabase $storeDatabase, StoreOwnerAccounts $storeOwnerAccounts): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null || $tenant->status !== TenantStatus::Provisioning) {
            return;
        }

        $databaseServerPlacement->place($tenant);
        $storeDatabase->prepare($tenant);

        $storeRegistration = StoreRegistration::query()->where('tenant_id', $tenant->id)->first();

        $hasOwner = $tenant->run(static function () use ($tenant, $storeRegistration, $storeOwnerAccounts): bool {
            if ($storeOwnerAccounts->hasOwner()) {
                return true;
            }

            if ($storeRegistration?->password_hash === null) {
                return false;
            }

            $storeOwnerAccounts->createOwner($tenant->owner_name, $tenant->owner_email, $storeRegistration->password_hash);

            return true;
        });

        if (! $hasOwner) {
            // Retrying can't bring the password back, so fail at once.
            $this->fail(new RuntimeException("Store {$tenant->id} has no owner and no password to create one with."));

            return;
        }

        $storeRegistration?->forceFill(['password_hash' => null])->save();
        $tenant->forceFill(['status' => TenantStatus::Active, 'provisioned_at' => CarbonImmutable::now()])->save();

        activity('stores')
            ->performedOn($tenant)
            ->event('store_provisioned')
            ->withProperties(['database_server' => $tenant->databaseServer?->public_id])
            ->log("Set up the store {$tenant->name}");

        $domain = $tenant->domains()->orderBy('id')->value('domain');

        Notification::route('mail', [$tenant->owner_email => $tenant->owner_name])
            ->notify(StoreReadyNotification::forStore($tenant, is_string($domain) ? $domain : ''));
    }

    /**
     * Marks the store as failed once every try has failed, so it shows up for platform admins instead of waiting forever.
     */
    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null || $tenant->status !== TenantStatus::Provisioning) {
            return;
        }

        $tenant->forceFill(['status' => TenantStatus::ProvisioningFailed])->save();

        activity('stores')
            ->performedOn($tenant)
            ->event('store_provisioning_failed')
            ->withProperties(['error' => $exception === null ? null : $exception::class])
            ->log("Setting up the store {$tenant->name} failed");
    }
}
