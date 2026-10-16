<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when replacement keys belong to another gateway account while payments or refunds on the current one are still in progress: those must finish with the account that took them.
 */
final class PaymentGatewayAccountChangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_gateway_account_changed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
