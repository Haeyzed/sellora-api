<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Tenancy\Exceptions\TermsOfServiceNotAcceptedException;
use App\Shared\Tenancy\TermsOfServiceAcceptance;

/**
 * The platform's record of who owns the current store: the owner contact, and the owners' acceptances of the terms of service.
 *
 * Implemented by Landlord, so a store's own code never writes to the central
 * database. The store database is the source of truth for who the owner is
 * (section 6): the store commits its change first, then a queued job brings
 * the platform up to date through update(), retrying until it succeeds. So
 * update() is safe to call any number of times, and anything that could make
 * the platform refuse is checked before the store commits.
 */
interface StoreOwnerContact
{
    /**
     * Checks, before the store commits, that the person is accepting the terms of service version in force.
     *
     * @param  string  $termsOfServiceId  Public ID of the terms of service version being accepted.
     *
     * @throws TermsOfServiceNotAcceptedException When that isn't the version in force, for example because a new one took effect meanwhile.
     */
    public function ensureTermsOfServiceInForce(string $termsOfServiceId): void;

    /**
     * Makes the platform's owner contact match the store's owner, and records their acceptance of the terms of service if given.
     *
     * Idempotent: calling it again with the same details changes nothing, and an acceptance is recorded once per reference.
     */
    public function update(string $name, string $email, ?TermsOfServiceAcceptance $acceptance = null): void;
}
