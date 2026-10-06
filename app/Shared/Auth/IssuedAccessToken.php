<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use Carbon\CarbonImmutable;

/**
 * A freshly issued sign-in token. The plain-text token exists only here, once; the database keeps a hash of it.
 */
final readonly class IssuedAccessToken
{
    public function __construct(
        public string $plainTextToken,
        public CarbonImmutable $expiresAt,
    ) {}
}
