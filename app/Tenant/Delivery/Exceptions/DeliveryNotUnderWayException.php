<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a driver's location is sent for a delivery that isn't picked up or on the way: it is only ever taken during a delivery.
 */
final class DeliveryNotUnderWayException extends DomainException
{
    public function errorCode(): string
    {
        return 'delivery_not_under_way';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
