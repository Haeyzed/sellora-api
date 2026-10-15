<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a change was made from an out-of-date view of the cart (its If-Match version isn't the current one), so it isn't applied. Read the cart again.
 */
final class CartChangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'cart_changed';
    }

    public function status(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
