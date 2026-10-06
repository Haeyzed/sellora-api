<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a role name is already used in this store, ignoring case, or is the reserved name "owner".
 */
final class RoleNameTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'role_name_taken';
    }

    public function fieldErrors(): array
    {
        return ['name' => [$this->translatedMessage()]];
    }
}
