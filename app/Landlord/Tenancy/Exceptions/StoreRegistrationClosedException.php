<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when sign-up can't open: the starting plan or a legal document in force is missing. A platform setup problem, not the merchant's.
 */
final class StoreRegistrationClosedException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_registration_closed';
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
