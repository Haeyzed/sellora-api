<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the owner starts an ownership transfer while another is still waiting. Cancel that one first.
 */
final class OwnershipTransferAlreadyPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'ownership_transfer_already_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
