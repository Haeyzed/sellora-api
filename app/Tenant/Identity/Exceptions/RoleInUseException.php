<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone tries to delete a role that staff members or pending invitations still have.
 */
final class RoleInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'role_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
