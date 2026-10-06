<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an ownership transfer was already accepted, cancelled or expired, so it can't be accepted or cancelled now.
 */
final class OwnershipTransferNotPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'ownership_transfer_not_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
