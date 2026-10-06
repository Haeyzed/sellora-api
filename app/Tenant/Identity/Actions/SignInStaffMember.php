<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CredentialCheck;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidCredentialsException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Shared\Auth\TwoFactor\PendingTwoFactorChallenge;
use App\Shared\Auth\TwoFactor\TwoFactorChallenges;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;

/**
 * Signs a staff member in to their store's dashboard with their email and password.
 *
 * With two-factor authentication on, a correct password gives a challenge to
 * answer with a code (CompleteStaffMemberSignIn) instead of a token.
 */
final readonly class SignInStaffMember
{
    public function __construct(
        private CredentialCheck $credentialCheck,
        private AccessTokenIssuer $accessTokenIssuer,
        private TwoFactorChallenges $twoFactorChallenges,
    ) {}

    /**
     * @param  string  $email  Already trimmed and lower-cased.
     *
     * @throws InvalidCredentialsException When the email or password is wrong.
     * @throws SignInTemporarilyLockedException After too many wrong attempts for this email in this store.
     * @throws AccountDeactivatedException When the password is right but the account is deactivated.
     */
    public function handle(string $email, string $password, string $deviceName): IssuedAccessToken|PendingTwoFactorChallenge
    {
        $staffMember = $this->credentialCheck->ensureValid(
            StaffMember::GUARD,
            $email,
            StaffMember::query()->where('email', $email)->first(),
            $password,
        );

        if (! $staffMember->is_active) {
            throw new AccountDeactivatedException;
        }

        if ($staffMember->hasTwoFactorEnabled()) {
            return $this->twoFactorChallenges->start($staffMember, StaffMember::GUARD, $deviceName);
        }

        $staffMember->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($staffMember, StaffMember::GUARD, $deviceName);
    }
}
