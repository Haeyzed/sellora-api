<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CredentialCheck;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Tenant\Delivery\Models\Driver;
use Carbon\CarbonImmutable;

/**
 * Signs a driver in to the driver app with their phone number and PIN.
 *
 * PINs are short, so the lockout after repeated wrong attempts is what keeps
 * them safe from guessing.
 */
final readonly class SignInDriver
{
    public function __construct(
        private CredentialCheck $credentialCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $phone  In E.164 format.
     *
     * @throws InvalidCredentialsException When the phone number or PIN is wrong.
     * @throws SignInTemporarilyLockedException After too many wrong attempts for this phone number in this store.
     * @throws AccountDeactivatedException When the PIN is right but the driver has been deactivated.
     */
    public function handle(string $phone, string $pin, string $deviceName): IssuedAccessToken
    {
        $driver = $this->credentialCheck->ensureValid(
            Driver::GUARD,
            $phone,
            Driver::query()->where('phone', $phone)->first(),
            $pin,
        );

        if (! $driver->is_active) {
            throw new AccountDeactivatedException;
        }

        $driver->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($driver, Driver::GUARD, $deviceName);
    }
}
