<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the gateway asked for can not take this order: not connected, turned off, not on the plan, not taking its currency or buyer country, or in the other mode (test or live).
 */
final class PaymentGatewayNotAvailableException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_not_available';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
