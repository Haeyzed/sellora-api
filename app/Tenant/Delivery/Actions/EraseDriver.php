<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Privacy\ErasureOutcome;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Delivery\Exceptions\DriverHoldsGoodsException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Privacy\DriverDataSubject;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;

/**
 * Erases a driver's personal data from every feature, all or nothing, at the driver's request made to staff.
 *
 * The account row stays, with every personal field cleared, so past
 * deliveries still point at someone. Business records about them are kept.
 */
final readonly class EraseDriver
{
    public function __construct(
        private PersonalDataRegistry $personalDataRegistry,
        private CurrentPasswordCheck $currentPasswordCheck,
        private DriverActivity $driverActivity,
        private DeactivateDriver $deactivateDriver,
    ) {}

    /**
     * Staff erasing a driver, confirming with the staff member's own password. Refused while the driver has goods; deliveries only assigned to them are taken back.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws TooManyIncorrectAttemptsException
     * @throws DriverHoldsGoodsException
     */
    public function byStaff(StaffMember $actor, Driver $driver, string $actorPassword): ErasureOutcome
    {
        $this->currentPasswordCheck->ensureCorrect($actor, $actorPassword);

        $subject = DriverDataSubject::of($driver);
        $keptUntil = $this->personalDataRegistry->keptByLawUntil($subject);

        DB::transaction(function () use ($actor, $driver, $subject): void {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $this->deactivateDriver->takeBackDeliveries($locked, AccountReference::to($actor));
            $this->personalDataRegistry->erase($subject);
        });

        $this->driverActivity->record($actor, $driver, 'driver_erased', 'Erased the personal data of');

        return new ErasureOutcome($keptUntil);
    }
}
