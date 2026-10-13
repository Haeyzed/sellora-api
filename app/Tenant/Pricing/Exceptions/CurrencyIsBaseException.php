<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when adding the store's base currency as an extra currency. Prices are already in it.
 */
final class CurrencyIsBaseException extends DomainException
{
    public function errorCode(): string
    {
        return 'currency_is_base';
    }

    public function fieldErrors(): array
    {
        return ['currency' => [$this->translatedMessage()]];
    }
}
