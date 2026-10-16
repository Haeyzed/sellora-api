<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the gateway did not answer while checking credentials. Nothing was saved; try again shortly.
 */
final class PaymentGatewayUnreachableException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_unreachable';
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
