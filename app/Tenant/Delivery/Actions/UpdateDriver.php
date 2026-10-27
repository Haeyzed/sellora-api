<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Exceptions\DriverPhoneTakenException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Changes a driver's name or phone number.
 *
 * A new phone number is a new way in, so it clears the PIN, signs the driver
 * out everywhere and stops any setup code working: staff then issue a new
 * code for the driver to choose a PIN with.
 */
final readonly class UpdateDriver
{
    public function __construct(
        private AccessTokenIssuer $accessTokenIssuer,
        private DriverSetupCodes $driverSetupCodes,
        private DriverActivity $driverActivity,
    ) {}

    /**
     * @param  string|null  $name  Null to leave it as it is.
     * @param  string|null  $phone  In E.164 format; null to leave it as it is.
     *
     * @throws DriverErasedException
     * @throws DriverPhoneTakenException When another driver signs in with that phone number.
     */
    public function handle(StaffMember $actor, Driver $driver, ?string $name, ?string $phone): Driver
    {
        return DB::transaction(function () use ($actor, $driver, $name, $phone): Driver {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($locked->isErased()) {
                throw new DriverErasedException;
            }

            if ($name !== null) {
                $locked->name = $name;
            }

            $isNewPhone = $phone !== null && $phone !== $locked->phone;

            if ($isNewPhone) {
                $locked->forceFill(['phone' => $phone, 'pin' => null]);
                $this->driverSetupCodes->voidLiveCodeOf($locked);
                $this->accessTokenIssuer->revokeAll($locked);
            }

            $changedFields = array_keys($locked->getDirty());

            try {
                $locked->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new DriverPhoneTakenException(previous: $exception);
            }

            if ($changedFields !== []) {
                $this->driverActivity->record($actor, $locked, 'driver_updated', 'Changed', $changedFields);
            }

            return $locked;
        });
    }
}
