<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor\Contracts;

/**
 * An account that can protect its sign-in with two-factor authentication (platform admins and staff).
 *
 * Implemented with the HasTwoFactorAuthentication trait, which stores the
 * encrypted secret, the hashed recovery codes and the last code used.
 */
interface TwoFactorAuthenticatable
{
    /**
     * Whether two-factor authentication is set up and confirmed, so sign-in asks for a code.
     */
    public function hasTwoFactorEnabled(): bool;

    /**
     * The name the authenticator app shows for this account, usually its email.
     */
    public function twoFactorAccountName(): string;
}
