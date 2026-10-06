<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a platform role name is already used, ignoring case.
 */
final class PlatformRoleNameTakenException extends DomainException
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
