<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when assigning a driver to a shipment that isn't one of the store's own deliveries waiting to leave, or that already has a delivery under way.
 */
final class ShipmentCannotBeAssignedException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipment_cannot_be_assigned';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
