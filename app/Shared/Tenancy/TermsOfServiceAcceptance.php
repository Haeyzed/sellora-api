<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Carbon\CarbonImmutable;

/**
 * Someone's acceptance of a terms of service version, as the store recorded it, for the platform to keep as evidence of the contract.
 */
final readonly class TermsOfServiceAcceptance
{
    /**
     * @param  string  $reference  Identifies what the acceptance was part of (for example an ownership transfer), so it is recorded once however often it is sent.
     * @param  string  $termsOfServiceId  Public ID of the terms of service version accepted.
     */
    public function __construct(
        public string $reference,
        public string $termsOfServiceId,
        public string $name,
        public string $email,
        public ?string $ipAddress,
        public ?string $userAgent,
        public CarbonImmutable $acceptedAt,
    ) {}
}
