<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use RuntimeException;

/**
 * Raised while setting up a store when no database server in its region has room, for example because the last place was taken after it registered.
 *
 * Not a merchant-facing error: setting up is retried, and a platform admin
 * adds capacity if it keeps failing.
 */
final class NoDatabaseServerAvailableException extends RuntimeException
{
    public function __construct(string $region)
    {
        parent::__construct("No database server in the \"{$region}\" region is accepting new stores.");
    }
}
