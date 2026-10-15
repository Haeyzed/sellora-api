<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when one line would ask for more of a variant than a line may (config cart.max_line_quantity), counting what is already there.
 */
final class CartLineQuantityTooHighException extends DomainException
{
    public function __construct(int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'cart_line_quantity_too_high';
    }

    public function fieldErrors(): array
    {
        return ['quantity' => [$this->translatedMessage()]];
    }
}
