<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Data;

use Carbon\CarbonImmutable;

/**
 * A one-time token confirming erasure for a customer without a password, given after they signed in again with a connected provider (section 3.3). Only its hash is kept.
 */
final readonly class ErasureConfirmation
{
    public function __construct(
        public string $token,
        public CarbonImmutable $expiresAt,
    ) {}
}
