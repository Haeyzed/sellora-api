<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a change would leave the platform with no active super admin, locking everyone out of managing the team.
 */
final class LastSuperAdminException extends DomainException
{
    public function errorCode(): string
    {
        return 'last_super_admin';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
