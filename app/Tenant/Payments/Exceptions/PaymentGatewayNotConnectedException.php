<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when changing or disconnecting a gateway the store has not connected.
 */
final class PaymentGatewayNotConnectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_not_connected';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
