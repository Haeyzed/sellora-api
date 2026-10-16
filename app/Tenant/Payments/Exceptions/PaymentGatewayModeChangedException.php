<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when replacement keys switch between test and live while payments or refunds are still in progress.
 */
final class PaymentGatewayModeChangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_mode_changed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
