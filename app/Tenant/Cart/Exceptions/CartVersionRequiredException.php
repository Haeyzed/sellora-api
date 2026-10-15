<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a change that replaces or removes something comes without the cart's version in If-Match.
 */
final class CartVersionRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'cart_version_required';
    }

    public function status(): int
    {
        return Response::HTTP_PRECONDITION_REQUIRED;
    }
}
