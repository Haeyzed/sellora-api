<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a customer tries to cancel an order they have paid for. Only the store can cancel a paid order, and refunds it.
 */
final class OrderAlreadyPaidException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_already_paid';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
