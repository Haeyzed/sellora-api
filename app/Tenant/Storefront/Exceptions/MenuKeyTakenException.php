<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another menu already uses the key.
 */
final class MenuKeyTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'menu_key_taken';
    }

    public function fieldErrors(): array
    {
        return ['key' => [$this->translatedMessage()]];
    }
}
