<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Exceptions\TwoFactorNotEnabledException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns two-factor authentication off for the signed-in account, removing its secret and recovery codes.
 *
 * Needs both the current password and a current code or recovery code, so
 * neither a stolen password nor a stolen token alone can remove the second
 * factor. Accounts that must use two-factor authentication (platform admins)
 * can only set it up again afterwards, for example on a new phone, before
 * doing anything else.
 */
final readonly class DisableTwoFactor
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
    ) {}

    /**
     * @throws TwoFactorNotEnabledException When two-factor authentication is already off.
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws InvalidTwoFactorCodeException When the code or recovery code is wrong, expired or already used.
     * @throws TooManyIncorrectAttemptsException After too many wrong current passwords or codes.
     */
    public function handle(Model&Authenticatable&TwoFactorAuthenticatable $account, string $currentPassword, ?string $code, ?string $recoveryCode): void
    {
        if (! $account->hasTwoFactorEnabled()) {
            throw new TwoFactorNotEnabledException;
        }

        $this->currentPasswordCheck->ensureCorrect($account, $currentPassword);
        $this->twoFactorAuthenticator->ensureValidCode($account, $code, $recoveryCode);

        $account->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();
    }
}
