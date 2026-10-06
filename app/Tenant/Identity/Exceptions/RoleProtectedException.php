<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone tries to rename, change or delete the built-in Owner role.
 */
final class RoleProtectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'role_protected';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
