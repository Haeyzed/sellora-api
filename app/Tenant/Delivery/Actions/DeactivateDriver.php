<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Stops a driver working for the store: they are signed out everywhere at once and can't sign in, and any PIN setup code stops working.
 *
 * The account is kept, so past deliveries still show who made them, and it
 * can be reactivated. It no longer counts towards the plan's driver limit.
 */
final readonly class DeactivateDriver
{
    public function __construct(
        private AccessTokenIssuer $accessTokenIssuer,
        private DriverSetupCodes $driverSetupCodes,
        private DriverActivity $driverActivity,
    ) {}

    public function handle(StaffMember $actor, Driver $driver): Driver
    {
        return DB::transaction(function () use ($actor, $driver): Driver {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                return $locked;
            }

            $locked->forceFill(['is_active' => false])->save();
            $this->accessTokenIssuer->revokeAll($locked);
            $this->driverSetupCodes->voidLiveCodeOf($locked);
            $this->driverActivity->record($actor, $locked, 'driver_deactivated', 'Deactivated');

            return $locked;
        });
    }
}
