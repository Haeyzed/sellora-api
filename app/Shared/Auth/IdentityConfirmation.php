<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Confirms it really is the signed-in person before an action that can't easily be undone, such as handing over or closing a store.
 *
 * Always the current password, plus an authenticator or recovery code when
 * the account uses two-factor authentication, so a stolen token alone is
 * never enough. Wrong passwords and codes count towards their lockouts.
 */
final readonly class IdentityConfirmation
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
    ) {}

    /**
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When the account uses two-factor authentication and the code is missing, wrong or used.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords or codes.
     */
    public function ensureConfirmed(Model&Authenticatable&TwoFactorAuthenticatable $account, string $currentPassword, ?string $code, ?string $recoveryCode): void
    {
        $this->currentPasswordCheck->ensureCorrect($account, $currentPassword);

        if ($account->hasTwoFactorEnabled()) {
            $this->twoFactorAuthenticator->ensureValidCode($account, $code, $recoveryCode);
        }
    }
}
