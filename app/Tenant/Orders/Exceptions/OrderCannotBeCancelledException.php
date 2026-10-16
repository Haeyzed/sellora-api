<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when cancelling an order that is already completed or cancelled, or that has started to ship. Once anything has shipped, returns (Step 18) take over.
 */
final class OrderCannotBeCancelledException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_cannot_be_cancelled';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
