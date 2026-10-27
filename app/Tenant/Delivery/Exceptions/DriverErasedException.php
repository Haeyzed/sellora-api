<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when changing a driver whose personal data was erased. The record stays only for their past deliveries.
 */
final class DriverErasedException extends DomainException
{
    public function errorCode(): string
    {
        return 'driver_erased';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
