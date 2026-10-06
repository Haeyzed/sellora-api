<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Exceptions\TwoFactorNotEnabledException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces all of the signed-in account's recovery codes with new ones, for example when they run low or may have been seen.
 */
final readonly class RegenerateRecoveryCodes
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
    ) {}

    /**
     * @return list<string> The new codes in plain text, shown only this once. The old ones stop working.
     *
     * @throws TwoFactorNotEnabledException When two-factor authentication is off.
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws TooManyIncorrectAttemptsException After too many wrong current passwords.
     */
    public function handle(Model&Authenticatable&TwoFactorAuthenticatable $account, string $currentPassword): array
    {
        if (! $account->hasTwoFactorEnabled()) {
            throw new TwoFactorNotEnabledException;
        }

        $this->currentPasswordCheck->ensureCorrect($account, $currentPassword);

        $recoveryCodes = $this->twoFactorAuthenticator->newRecoveryCodes();

        $account->forceFill([
            'two_factor_recovery_codes' => array_map($this->twoFactorAuthenticator->hashRecoveryCode(...), $recoveryCodes),
        ])->save();

        return $recoveryCodes;
    }
}
