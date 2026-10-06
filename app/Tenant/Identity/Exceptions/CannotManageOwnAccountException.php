<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when staff try to change their own roles or deactivate themselves, which could lock them or the store out.
 */
final class CannotManageOwnAccountException extends DomainException
{
    public function errorCode(): string
    {
        return 'cannot_manage_own_account';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
