<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a return is asked for on an order that was never sold (never paid in full while open), so nothing it holds can be given back by a return, or whose personal data was anonymised.
 */
final class OrderNotReturnableException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_not_returnable';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
