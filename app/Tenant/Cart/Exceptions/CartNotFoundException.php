<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the shopper has no cart, or the cart token sent finds none: a wrong token and a missing one look the same.
 */
final class CartNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'cart_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
