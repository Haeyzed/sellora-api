<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Auth\Exceptions\TwoFactorAlreadyEnabledException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use App\Shared\Auth\TwoFactor\TwoFactorSetup;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a new authenticator secret for the signed-in account, to add to an authenticator app.
 *
 * Nothing changes at sign-in until the setup is confirmed with a code from the
 * app. Starting again replaces an unconfirmed secret.
 */
final readonly class StartTwoFactorSetup
{
    public function __construct(
        private CurrentPasswordCheck $currentPasswordCheck,
        private TwoFactorAuthenticator $twoFactorAuthenticator,
    ) {}

    /**
     * @throws TwoFactorAlreadyEnabledException When two-factor authentication is already on.
     * @throws IncorrectCurrentPasswordException When the current password is wrong.
     * @throws TooManyIncorrectAttemptsException After too many wrong current passwords.
     */
    public function handle(Model&Authenticatable&TwoFactorAuthenticatable $account, string $currentPassword): TwoFactorSetup
    {
        if ($account->hasTwoFactorEnabled()) {
            throw new TwoFactorAlreadyEnabledException;
        }

        $this->currentPasswordCheck->ensureCorrect($account, $currentPassword);

        $secret = $this->twoFactorAuthenticator->newSecret();

        $account->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        return new TwoFactorSetup($secret, $this->twoFactorAuthenticator->setupUrl($account->twoFactorAccountName(), $secret));
    }
}
