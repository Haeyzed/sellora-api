<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores an account's two-factor settings: the authenticator secret (encrypted), hashed one-time recovery codes, when setup was confirmed, and the last code used, so no code works twice.
 *
 * The model needs the two_factor_* columns and must implement TwoFactorAuthenticatable.
 *
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 *
 * @mixin Model
 */
trait HasTwoFactorAuthentication
{
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function twoFactorAccountName(): string
    {
        return (string) $this->getAttribute('email');
    }

    /**
     * Encrypts the secret and keeps every two-factor column out of serialised output.
     */
    protected function initializeHasTwoFactorAuthentication(): void
    {
        $this->mergeCasts([
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_used_timestep' => 'integer',
        ]);

        $this->makeHidden(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_used_timestep']);
    }
}
