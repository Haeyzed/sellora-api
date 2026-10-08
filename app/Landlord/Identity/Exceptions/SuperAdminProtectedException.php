<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone tries to give a super admin permissions directly: a super admin already may do everything, and is changed only through their roles.
 */
final class SuperAdminProtectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'super_admin_protected';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
