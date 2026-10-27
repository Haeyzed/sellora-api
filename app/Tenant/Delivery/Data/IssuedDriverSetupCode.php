<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Data;

use App\Tenant\Delivery\Models\Driver;
use Carbon\CarbonImmutable;

/**
 * A PIN setup code just issued to a driver: the only moment the code itself exists outside the driver's hands.
 */
final readonly class IssuedDriverSetupCode
{
    public function __construct(
        public Driver $driver,
        public string $code,
        public CarbonImmutable $expiresAt,
    ) {}
}
