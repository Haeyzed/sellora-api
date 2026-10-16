<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when moving a shipment to a status it can't reach from where it is, such as shipping a pickup or cancelling one that has left.
 */
final class ShipmentTransitionNotAllowedException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipment_transition_not_allowed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
