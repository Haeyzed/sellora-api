<?php

declare(strict_types=1);

namespace App\Shared\Auth\TwoFactor;

/**
 * A new, not yet confirmed authenticator secret, and the link that adds it to an authenticator app.
 */
final readonly class TwoFactorSetup
{
    public function __construct(
        public string $secret,
        public string $setupUrl,
    ) {}
}
