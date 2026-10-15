<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a zone would both cover the rest of the world and list regions of its own: it covers whatever no other zone lists, so it lists none.
 */
final class RestOfWorldZoneHasRegionsException extends DomainException
{
    public function errorCode(): string
    {
        return 'rest_of_world_zone_has_regions';
    }
}
