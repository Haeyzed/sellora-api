<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TwoFactorSetupNotStartedException;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Contracts\HasApiTokens;

/**
 * Turns two-factor authentication on once the person proves their authenticator app works, and gives them recovery codes.
 *
 * Every other device is signed out, because those sessions started without
 * a second factor; the device confirming stays signed in.
 */
final readonly class ConfirmTwoFactorSetup
{
    public function __construct(
        private TwoFactorAuthenticator $twoFactorAuthenticator,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @return list<string> The recovery codes in plain text, shown only this once.
     *
     * @throws TwoFactorSetupNotStartedException When setup wasn't started, or is already confirmed.
     * @throws InvalidTwoFactorCodeException When the code from the app is wrong.
     */
    public function handle(Model&HasApiTokens&TwoFactorAuthenticatable $account, string $code): array
    {
        $secret = $account->getAttribute('two_factor_secret');

        if (! is_string($secret) || $account->hasTwoFactorEnabled()) {
            throw new TwoFactorSetupNotStartedException;
        }

        if (! $this->twoFactorAuthenticator->verifyCode($account, $secret, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $recoveryCodes = $this->twoFactorAuthenticator->newRecoveryCodes();

        $account->forceFill([
            'two_factor_confirmed_at' => CarbonImmutable::now(),
            'two_factor_recovery_codes' => array_map($this->twoFactorAuthenticator->hashRecoveryCode(...), $recoveryCodes),
        ])->save();

        $this->accessTokenIssuer->revokeAllExceptCurrent($account);

        return $recoveryCodes;
    }
}
