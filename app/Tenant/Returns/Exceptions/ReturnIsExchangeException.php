<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a return settled by an exchange is asked to be refunded: confirm its exchange instead, or change its resolution to a refund first.
 */
final class ReturnIsExchangeException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_is_an_exchange';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
