<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a payment method paid by hand is off, or doesn't fit the order: cash on delivery only for an order paid on delivery, and never for a test order.
 */
final class PaymentMethodNotAvailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_method_not_available';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
