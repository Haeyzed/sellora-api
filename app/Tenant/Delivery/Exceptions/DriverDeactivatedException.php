<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when issuing a PIN setup code to a deactivated driver. Reactivate them first.
 */
final class DriverDeactivatedException extends DomainException
{
    public function errorCode(): string
    {
        return 'driver_deactivated';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
