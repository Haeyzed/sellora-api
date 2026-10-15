<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a new variant would take a cart past the most different variants it may hold (config cart.max_lines).
 */
final class CartFullException extends DomainException
{
    public function __construct(int $limit)
    {
        parent::__construct(['limit' => $limit]);
    }

    public function errorCode(): string
    {
        return 'cart_full';
    }
}
