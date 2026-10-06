<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;

/**
 * Records a store's new owner on the platform side, while tenancy is initialized for that store.
 *
 * Implemented by Landlord, which keeps the owner contact and the legal
 * acceptances, so a store's own code never writes to the central database.
 * The store's contract with Sellora moves to the new owner, so they accept
 * the terms of service in force as they take over.
 */
interface StoreOwnership
{
    /**
     * Records that the person accepted the terms of service in force and makes them the store's owner contact.
     *
     * @param  string  $acceptedTermsOfServiceId  Public ID of the terms of service version the new owner accepted.
     *
     * @throws TermsOfServiceNotAcceptedException When that isn't the version in force, for example because a new one took effect meanwhile.
     */
    public function recordNewOwner(string $name, string $email, string $acceptedTermsOfServiceId, ?string $ipAddress, ?string $userAgent): void;
}
