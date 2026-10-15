<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a variant can't be put in a cart: its product isn't published, it has no price, or it is in the trash.
 */
final class VariantNotAvailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'variant_not_available';
    }

    public function fieldErrors(): array
    {
        return ['variant' => [$this->translatedMessage()]];
    }
}
