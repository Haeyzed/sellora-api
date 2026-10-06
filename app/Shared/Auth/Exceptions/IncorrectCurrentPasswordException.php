<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when someone changing their password gets their current password wrong.
 */
final class IncorrectCurrentPasswordException extends DomainException
{
    public function errorCode(): string
    {
        return 'current_password_incorrect';
    }

    public function fieldErrors(): array
    {
        return ['current_password' => [$this->translatedMessage()]];
    }
}
