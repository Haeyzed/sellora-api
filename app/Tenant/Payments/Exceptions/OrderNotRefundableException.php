<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when refunding an order that holds nothing to give back: unpaid, or still open and not paid in full (cancel it first to give back part of a payment).
 */
final class OrderNotRefundableException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_not_refundable';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
