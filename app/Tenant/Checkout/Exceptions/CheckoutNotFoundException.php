<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the shopper has no open checkout for their cart. Start one.
 */
final class CheckoutNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'checkout_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
