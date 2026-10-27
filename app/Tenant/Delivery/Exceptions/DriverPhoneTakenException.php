<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when another of the store's drivers already signs in with that phone number.
 */
final class DriverPhoneTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'driver_phone_taken';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
