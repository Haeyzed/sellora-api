<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Carbon\CarbonImmutable;

/**
 * A store export as its requester sees it, for the store zone, which can't read the platform's records.
 */
final readonly class StoreExportSummary
{
    /**
     * @param  string  $status  "queued", "building", "ready", "failed" or "expired".
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?int $sizeBytes,
        public CarbonImmutable $requestedAt,
        public ?CarbonImmutable $readyAt,
        public CarbonImmutable $expiresAt,
    ) {}
}
