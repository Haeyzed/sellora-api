<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a return's exchange is previewed or confirmed before it can be: it must be an exchange, and inspected (or approved with the buyer keeping the items).
 */
final class ReturnExchangeNotReadyException extends DomainException
{
    public function errorCode(): string
    {
        return 'exchange_not_ready';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
