<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when deleting a platform role that admins or pending invitations still have.
 */
final class PlatformRoleInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_role_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
