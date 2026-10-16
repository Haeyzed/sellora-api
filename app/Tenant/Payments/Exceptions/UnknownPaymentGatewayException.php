<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when no payment gateway uses the key asked for.
 */
final class UnknownPaymentGatewayException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
