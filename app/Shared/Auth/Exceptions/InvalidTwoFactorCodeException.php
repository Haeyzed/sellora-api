<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a two-factor code or recovery code is wrong, expired or already used.
 */
final class InvalidTwoFactorCodeException extends DomainException
{
    /**
     * @param  string  $field  The input the wrong code was typed into ("code" or "recovery_code").
     */
    public function __construct(private readonly string $field = 'code')
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'two_factor_code_invalid';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
