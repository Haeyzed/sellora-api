<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when another zone already covers the rest of the world: only one can.
 */
final class RestOfWorldZoneExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'rest_of_world_zone_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
