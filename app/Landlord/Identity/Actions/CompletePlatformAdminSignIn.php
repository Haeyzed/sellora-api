<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\AccountDeactivatedException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorChallengeException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\SignInTemporarilyLockedException;
use App\Shared\Auth\IssuedAccessToken;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use App\Shared\Auth\TwoFactor\TwoFactorChallenges;
use Carbon\CarbonImmutable;

/**
 * Finishes a platform admin's sign-in with a code from their authenticator app, or a recovery code.
 */
final readonly class CompletePlatformAdminSignIn
{
    public function __construct(
        private TwoFactorChallenges $twoFactorChallenges,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $challengeToken  The token sign-in returned after the correct password.
     *
     * @throws InvalidTwoFactorChallengeException When the challenge is unknown, expired or already answered.
     * @throws AccountDeactivatedException When the account was deactivated after the password step.
     * @throws SignInTemporarilyLockedException After too many wrong codes for this account.
     * @throws InvalidTwoFactorCodeException When the code is wrong, expired or already used.
     */
    public function handle(string $challengeToken, ?string $code, ?string $recoveryCode): IssuedAccessToken
    {
        $challenge = $this->twoFactorChallenges->find(PlatformAdmin::GUARD, $challengeToken);
        $platformAdmin = $challenge === null ? null : PlatformAdmin::query()->find($challenge->accountKey);

        if ($challenge === null || $platformAdmin === null) {
            throw new InvalidTwoFactorChallengeException;
        }

        if (! $platformAdmin->is_active) {
            $this->twoFactorChallenges->forget(PlatformAdmin::GUARD, $challengeToken);

            throw new AccountDeactivatedException;
        }

        $this->twoFactorAuthenticator->ensureValidForSignIn($platformAdmin, $code, $recoveryCode);
        $this->twoFactorChallenges->forget(PlatformAdmin::GUARD, $challengeToken);

        $platformAdmin->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();

        return $this->accessTokenIssuer->issue($platformAdmin, PlatformAdmin::GUARD, $challenge->deviceName);
    }
}
