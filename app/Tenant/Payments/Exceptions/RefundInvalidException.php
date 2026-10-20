<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a refund's parts don't fit the order: an unknown line, more units than are left to give back, shipping already given back or never charged, an amount in another currency, or a payment that isn't the order's or can't give money back.
 */
final class RefundInvalidException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_invalid';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
