<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a request reaches a store that isn't serving requests, such as one still being set up.
 */
final class StoreUnavailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_unavailable';
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
