<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a refund asks for more than the payments drawn on can still give back: what they received, less what is already refunded or on its way back.
 */
final class RefundExceedsRefundableException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_exceeds_refundable';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
