<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use Carbon\CarbonImmutable;

/**
 * The driver's account. Erasing it clears the name, phone and PIN and keeps the row, so past deliveries still point at someone and the phone number is free again.
 *
 * Registered last, so the driver's other sections are erased while the
 * account is still whole.
 */
final readonly class DriverAccountData implements PersonalDataHandler
{
    public function __construct(private DriverSetupCodes $driverSetupCodes) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        $driver = DriverDataSubject::driver($subject);

        if ($driver === null) {
            return [];
        }

        return [[
            'id' => $driver->public_id,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'is_active' => $driver->is_active,
            'has_pin' => $driver->pin !== null,
            'last_signed_in_at' => $driver->last_signed_in_at?->toIso8601String(),
            'created_at' => $driver->created_at?->toIso8601String(),
        ]];
    }

    /**
     * Clears the name, phone and PIN, deactivates the account, signs the driver out everywhere and stops any setup code working.
     */
    public function erase(DataSubject $subject): void
    {
        $driver = DriverDataSubject::driver($subject, lock: true);

        if ($driver === null || $driver->isErased()) {
            return;
        }

        $driver->tokens()->delete();
        $this->driverSetupCodes->voidLiveCodeOf($driver);

        $driver->forceFill([
            'name' => null,
            'phone' => null,
            'pin' => null,
            'is_active' => false,
            'erased_at' => CarbonImmutable::now(),
        ])->save();
    }
}
