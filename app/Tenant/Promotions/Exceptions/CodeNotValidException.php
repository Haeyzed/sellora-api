<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised for any code that can't be used now: unknown, switched off, not started, ended, used up, or its promotion archived or deactivated.
 *
 * Always the same answer, so trying codes reveals nothing about which exist.
 */
final class CodeNotValidException extends DomainException
{
    public function errorCode(): string
    {
        return 'code_not_valid';
    }

    public function fieldErrors(): array
    {
        return ['code' => [$this->translatedMessage()]];
    }
}
