<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a guest tries to check out in a store that only sells to customers with an account. Sign in first.
 */
final class GuestCheckoutNotAllowedException extends DomainException
{
    public function errorCode(): string
    {
        return 'guest_checkout_not_allowed';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
