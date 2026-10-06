<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when someone tries to give the Owner role through an invitation or a role change. Ownership only moves by an ownership transfer.
 */
final class RoleNotAssignableException extends DomainException
{
    public function errorCode(): string
    {
        return 'role_not_assignable';
    }
}
