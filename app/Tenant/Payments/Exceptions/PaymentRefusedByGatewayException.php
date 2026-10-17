<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the gateway refused to start a payment. Nothing was charged; try again or choose another way to pay.
 */
final class PaymentRefusedByGatewayException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_refused_by_gateway';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
