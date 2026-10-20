<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when refunds that reduce the sale would add up to more than the order's total. Money paid over the total is given back as an overpayment instead.
 */
final class RefundExceedsSaleException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_exceeds_sale';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
