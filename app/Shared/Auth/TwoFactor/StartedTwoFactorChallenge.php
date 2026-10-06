<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

/**
 * What a two-factor challenge remembers between the password and the code: whose sign-in it is, and for which device.
 */
final readonly class StartedTwoFactorChallenge
{
    public function __construct(
        public int|string $accountKey,
        public string $deviceName,
    ) {}
}
