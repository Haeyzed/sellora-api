<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when changing or deleting the built-in Super Admin role.
 */
final class PlatformRoleProtectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_role_protected';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
