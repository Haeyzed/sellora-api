<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an overpayment given back on an order that was sold would be more than was paid over its total. The rest reduces the sale, as a refund of its own.
 */
final class RefundExceedsOverpaymentException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_exceeds_overpayment';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
