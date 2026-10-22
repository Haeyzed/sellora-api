<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the cart can't be checked out as it stands: it is empty, or none of its lines can be bought.
 */
final class CheckoutCannotStartException extends DomainException
{
    public function errorCode(): string
    {
        return 'checkout_cannot_start';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
