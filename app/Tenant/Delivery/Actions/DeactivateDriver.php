<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Exceptions\DriverHoldsGoodsException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Stops a driver working for the store: they are signed out everywhere at once and can't sign in, and any PIN setup code stops working.
 *
 * Refused while they have goods (a delivery picked up, on the way, or failed
 * and not yet resolved); deliveries only assigned to them are taken back.
 * Locks the driver first, which assigning a driver also does, so nothing is
 * left assigned to a deactivated driver. The account is kept, so past
 * deliveries still show who made them, and it can be reactivated. It no
 * longer counts towards the plan's driver limit.
 */
final readonly class DeactivateDriver
{
    public function __construct(
        private AccessTokenIssuer $accessTokenIssuer,
        private DriverSetupCodes $driverSetupCodes,
        private DriverActivity $driverActivity,
        private CancelDelivery $cancelDelivery,
    ) {}

    /**
     * @throws DriverHoldsGoodsException
     */
    public function handle(StaffMember $actor, Driver $driver): Driver
    {
        return DB::transaction(function () use ($actor, $driver): Driver {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                return $locked;
            }

            $this->takeBackDeliveries($locked, AccountReference::to($actor));
            $locked->forceFill(['is_active' => false])->save();
            $this->accessTokenIssuer->revokeAll($locked);
            $this->driverSetupCodes->voidLiveCodeOf($locked);
            $this->driverActivity->record($actor, $locked, 'driver_deactivated', 'Deactivated');

            return $locked;
        });
    }

    /**
     * Refuses a locked driver who has goods, and cancels the deliveries only assigned to them. Run under the driver's lock.
     *
     * @throws DriverHoldsGoodsException
     */
    public function takeBackDeliveries(Driver $locked, AccountReference $by): void
    {
        if ($locked->deliveries()->holdingGoods()->exists()) {
            throw new DriverHoldsGoodsException;
        }

        foreach ($locked->deliveries()->open()->orderBy('id')->get() as $assigned) {
            $this->cancelDelivery->ifAssigned($assigned, $by);
        }
    }
}
