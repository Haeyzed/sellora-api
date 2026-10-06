<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a super admin tries to change their own roles, deactivate themselves or reset their own two-factor authentication.
 */
final class CannotManageOwnPlatformAccountException extends DomainException
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
