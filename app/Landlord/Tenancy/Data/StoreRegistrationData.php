<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Data;

/**
 * What a merchant enters to register a store.
 */
final readonly class StoreRegistrationData
{
    /**
     * @param  string  $subdomain  Lower-case, already checked against the format and the reserved list.
     * @param  string  $email  Normalised (trimmed, lower-case).
     * @param  string  $timezone  The chosen timezone, or the country's only one.
     * @param  list<string>  $acceptedLegalDocumentIds  Public IDs of the legal document versions the merchant accepted.
     */
    public function __construct(
        public string $storeName,
        public string $subdomain,
        public string $ownerName,
        public string $email,
        public string $password,
        public string $countryCode,
        public string $timezone,
        public string $hostingRegion,
        public array $acceptedLegalDocumentIds,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
