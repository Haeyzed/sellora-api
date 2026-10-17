<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when paying an order that is not waiting for payment: cancelled, completed, already paid, or past its payment deadline.
 */
final class OrderNotPayableException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_not_payable';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
