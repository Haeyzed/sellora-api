<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

use Carbon\CarbonImmutable;

/**
 * A two-factor challenge as handed to the person signing in: the token to send back with their code, and when it expires.
 */
final readonly class PendingTwoFactorChallenge
{
    public function __construct(
        public string $challengeToken,
        public CarbonImmutable $expiresAt,
    ) {}
}
