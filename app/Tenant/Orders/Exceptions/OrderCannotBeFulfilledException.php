<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when shipping an order that isn't open, isn't paid yet, or has nothing that ships. Pay-on-delivery comes as a payment method that allows it (Step 15).
 */
final class OrderCannotBeFulfilledException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_cannot_be_fulfilled';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
