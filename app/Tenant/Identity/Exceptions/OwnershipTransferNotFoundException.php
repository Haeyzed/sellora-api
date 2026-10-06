<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when there is no pending ownership transfer that the signed-in staff member is part of.
 */
final class OwnershipTransferNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'ownership_transfer_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
