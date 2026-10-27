<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Delivery\Data\IssuedDriverSetupCode;
use App\Tenant\Delivery\Exceptions\DriverPhoneTakenException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverLimit;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds a driver to the store, without a PIN, and issues their first PIN setup code.
 *
 * The driver can't sign in until they choose a PIN with the code. Counts
 * towards the plan's driver limit, checked under a lock so two requests
 * can't both take the last place.
 */
final readonly class CreateDriver
{
    public function __construct(
        private DriverLimit $driverLimit,
        private IssueDriverSetupCode $issueDriverSetupCode,
        private DriverActivity $driverActivity,
    ) {}

    /**
     * @param  string  $phone  In E.164 format.
     *
     * @throws UsageLimitReachedException When the plan has no room for another active driver.
     * @throws DriverPhoneTakenException When another driver signs in with that phone number.
     */
    public function handle(StaffMember $actor, string $name, string $phone): IssuedDriverSetupCode
    {
        return DB::transaction(function () use ($actor, $name, $phone): IssuedDriverSetupCode {
            $this->driverLimit->ensureRoomForOneMore();

            $driver = new Driver;
            $driver->forceFill(['name' => $name, 'phone' => $phone, 'is_active' => true]);

            try {
                $driver->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new DriverPhoneTakenException(previous: $exception);
            }

            $this->driverActivity->record($actor, $driver, 'driver_created', 'Added');

            return $this->issueDriverSetupCode->handle($actor, $driver);
        });
    }
}
