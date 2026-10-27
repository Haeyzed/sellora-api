<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\AccountReference;
use App\Tenant\Delivery\Data\IssuedDriverSetupCode;
use App\Tenant\Delivery\Exceptions\DriverDeactivatedException;
use App\Tenant\Delivery\Exceptions\DriverErasedException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverSetupCode;
use App\Tenant\Delivery\Services\DriverActivity;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gives a driver a one-time code to choose their own PIN with, for a new driver or one who forgot their PIN or lost their phone.
 *
 * Staff never set or know the PIN. Issuing a code at once clears the old
 * PIN, signs the driver out everywhere and stops any earlier code working,
 * because the usual reason is a lost phone. The code is returned here only;
 * just its keyed hash is stored. Every issue goes in the activity log.
 */
final readonly class IssueDriverSetupCode
{
    public function __construct(
        private DriverSetupCodes $driverSetupCodes,
        private AccessTokenIssuer $accessTokenIssuer,
        private DriverActivity $driverActivity,
    ) {}

    /**
     * @throws DriverErasedException
     * @throws DriverDeactivatedException When the driver is deactivated; reactivate them first.
     */
    public function handle(StaffMember $actor, Driver $driver): IssuedDriverSetupCode
    {
        return DB::transaction(function () use ($actor, $driver): IssuedDriverSetupCode {
            $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($locked->isErased()) {
                throw new DriverErasedException;
            }

            if (! $locked->is_active) {
                throw new DriverDeactivatedException;
            }

            $this->driverSetupCodes->voidLiveCodeOf($locked);
            $locked->forceFill(['pin' => null])->save();
            $this->accessTokenIssuer->revokeAll($locked);

            $code = $this->driverSetupCodes->generate();
            $expiresAt = CarbonImmutable::now()->addHours(config()->integer('delivery.setup_codes.valid_for_hours'));
            $issuer = AccountReference::to($actor);

            (new DriverSetupCode)->forceFill([
                'driver_id' => $locked->id,
                'code_hash' => $this->driverSetupCodes->hash($code),
                'expires_at' => $expiresAt,
                'issued_by_type' => $issuer->type,
                'issued_by_id' => $issuer->publicId,
            ])->save();

            $this->driverActivity->record($actor, $locked, 'driver_setup_code_issued', 'Issued a PIN setup code to');

            return new IssuedDriverSetupCode($locked, $code, $expiresAt);
        });
    }
}
