<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorChallengeException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use App\Shared\Auth\TwoFactor\TwoFactorChallenges;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;

/**
 * Finishes a staff member's sign-in with a code from their authenticator app, or a recovery code.
 */
final readonly class CompleteStaffMemberSignIn
{
    public function __construct(
        private TwoFactorChallenges $twoFactorChallenges,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $challengeToken  The token sign-in returned after the correct password.
     *
     * @throws InvalidTwoFactorChallengeException When the challenge is unknown, expired, already answered or from another store.
     * @throws AccountDeactivatedException When the account was deactivated after the password step.
     * @throws SignInTemporarilyLockedException After too many wrong codes for this account.
     * @throws InvalidTwoFactorCodeException When the code is wrong, expired or already used.
     */
    public function handle(string $challengeToken, ?string $code, ?string $recoveryCode): IssuedAccessToken
    {
        $challenge = $this->twoFactorChallenges->find(StaffMember::GUARD, $challengeToken);
        $staffMember = $challenge === null ? null : StaffMember::query()->find($challenge->accountKey);

        if ($challenge === null || $staffMember === null) {
            throw new InvalidTwoFactorChallengeException;
        }

        if (! $staffMember->is_active) {
            $this->twoFactorChallenges->forget(StaffMember::GUARD, $challengeToken);

            throw new AccountDeactivatedException;
        }

        $this->twoFactorAuthenticator->ensureValidForSignIn($staffMember, $code, $recoveryCode);
        $this->twoFactorChallenges->forget(StaffMember::GUARD, $challengeToken);

        $staffMember->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($staffMember, StaffMember::GUARD, $challenge->deviceName);
    }
}
