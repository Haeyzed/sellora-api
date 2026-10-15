<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a cart's state isn't one of its country's states (the country the shopper gave, or the store's own), or that country has no states list.
 */
final class StateNotInCountryException extends DomainException
{
    public function errorCode(): string
    {
        return 'state_not_in_country';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['state_code' => [$this->translatedMessage()]];
    }
}
