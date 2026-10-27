<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Delivery\Exceptions\InvalidDriverSetupCodeException;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Models\DriverSetupCode;
use App\Tenant\Delivery\Services\DriverSetupCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A driver choosing their own PIN with the one-time code staff gave them, which also signs them in.
 *
 * The code works once. A wrong code is counted even though the request
 * fails, under a lock on the code, so racing requests can't get more tries;
 * after too many it stops working and staff issue a new one. Any other
 * device the driver was signed in on is signed out.
 */
final readonly class ChooseDriverPin
{
    public function __construct(
        private DriverSetupCodes $driverSetupCodes,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $phone  In E.164 format.
     *
     * @throws InvalidDriverSetupCodeException When the code is wrong, used, expired or voided, or the phone number has none.
     * @throws AccountDeactivatedException When the code is right but the driver has been deactivated.
     */
    public function handle(string $phone, string $code, string $pin, string $deviceName): IssuedAccessToken
    {
        $driver = Driver::query()->where('phone', $phone)->first();

        $issuedAccessToken = DB::transaction(function () use ($driver, $code, $pin, $deviceName): ?IssuedAccessToken {
            $setupCode = $driver === null ? null : DriverSetupCode::query()->where('driver_id', $driver->id)->live()->lockForUpdate()->first();

            if ($setupCode === null || ! $setupCode->expires_at->isFuture()) {
                // Hashed anyway, so a phone number without a code answers as fast as one with.
                $this->driverSetupCodes->hash($code);

                throw new InvalidDriverSetupCodeException;
            }

            if (! $this->driverSetupCodes->matches($code, $setupCode->code_hash)) {
                // Returning, not throwing, so the wrong try is committed.
                $this->countWrongTry($setupCode);

                return null;
            }

            /** @var Driver $driver */
            return $this->chooseWith($setupCode, $driver, $pin, $deviceName);
        });

        if ($issuedAccessToken === null) {
            throw new InvalidDriverSetupCodeException;
        }

        return $issuedAccessToken;
    }

    private function countWrongTry(DriverSetupCode $setupCode): void
    {
        $setupCode->failed_attempts++;

        if ($setupCode->failed_attempts >= config()->integer('delivery.setup_codes.max_failed_attempts')) {
            $setupCode->voided_at = CarbonImmutable::now();
        }

        $setupCode->save();
    }

    /**
     * @throws AccountDeactivatedException
     */
    private function chooseWith(DriverSetupCode $setupCode, Driver $driver, string $pin, string $deviceName): IssuedAccessToken
    {
        $locked = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

        if (! $locked->is_active) {
            throw new AccountDeactivatedException;
        }

        $setupCode->forceFill(['used_at' => CarbonImmutable::now()])->save();
        $locked->forceFill(['pin' => $pin, 'last_signed_in_at' => CarbonImmutable::now()])->save();
        $this->accessTokenIssuer->revokeAll($locked);

        return $this->accessTokenIssuer->issue($locked, Driver::GUARD, $deviceName);
    }
}
