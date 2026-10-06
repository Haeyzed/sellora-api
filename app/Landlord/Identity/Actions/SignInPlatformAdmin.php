<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CredentialCheck;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use Carbon\CarbonImmutable;

/**
 * Signs a member of Sellora's team in to the platform admin app with their email and password.
 */
final readonly class SignInPlatformAdmin
{
    public function __construct(
        private CredentialCheck $credentialCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     *
     * @throws InvalidCredentialsException When the email or password is wrong.
     * @throws SignInTemporarilyLockedException After too many wrong attempts for this email.
     * @throws AccountDeactivatedException When the password is right but the account is deactivated.
     */
    public function handle(string $email, string $password, string $deviceName): IssuedAccessToken
    {
        $platformAdmin = $this->credentialCheck->ensureValid(
            PlatformAdmin::GUARD,
            $email,
            PlatformAdmin::query()->where('email', $email)->first(),
            $password,
        );

        if (! $platformAdmin->is_active) {
            throw new AccountDeactivatedException;
        }

        $platformAdmin->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($platformAdmin, PlatformAdmin::GUARD, $deviceName);
    }
}
