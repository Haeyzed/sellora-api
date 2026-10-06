<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone invites, or accepts an invitation for, an email that already belongs to a staff member of this store.
 */
final class StaffMemberAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'staff_member_already_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
