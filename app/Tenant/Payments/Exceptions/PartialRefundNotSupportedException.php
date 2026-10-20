<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when part of a payment is to go back through a gateway that only refunds whole payments.
 */
final class PartialRefundNotSupportedException extends DomainException
{
    public function errorCode(): string
    {
        return 'partial_refund_not_supported';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
