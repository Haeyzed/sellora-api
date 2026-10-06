<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a store isn't in a status the change applies to, such as suspending a store that is still being set up.
 */
final class StoreStatusConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_status_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
