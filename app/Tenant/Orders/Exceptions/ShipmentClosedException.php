<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when changing the tracking of a shipment that has arrived or was cancelled.
 */
final class ShipmentClosedException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipment_closed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
