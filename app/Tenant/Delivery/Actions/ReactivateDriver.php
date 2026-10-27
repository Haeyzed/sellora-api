<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverLimit;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Lets a deactivated driver work for the store again, within the plan's driver limit.
 *
 * They sign in with their old PIN, if they had one; otherwise staff issue a
 * setup code.
 */
final readonly class ReactivateDriver
{
    public function __construct(
        private DriverLimit $driverLimit,
        private DriverActivity $driverActivity,
    ) {}

    /**
     * @throws DriverErasedException
     * @throws UsageLimitReachedException When the plan has no room for another active driver.
     */
    public function handle(StaffMember $actor, Driver $driver): Driver
    {
        return DB::transaction(function () use ($actor, $driver): Driver {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($locked->isErased()) {
                throw new DriverErasedException;
            }

            if ($locked->is_active) {
                return $locked;
            }

            $this->driverLimit->ensureRoomForOneMore();
            $locked->forceFill(['is_active' => true])->save();
            $this->driverActivity->record($actor, $locked, 'driver_reactivated', 'Reactivated');

            return $locked;
        });
    }
}
