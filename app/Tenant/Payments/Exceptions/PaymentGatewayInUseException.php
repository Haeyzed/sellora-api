<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when disconnecting a gateway that still has payments or refunds in progress. Disabling it (no longer offered) is always allowed.
 */
final class PaymentGatewayInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
