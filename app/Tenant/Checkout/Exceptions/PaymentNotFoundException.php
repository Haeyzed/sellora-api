<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a buyer comes back from a gateway with a payment reference this store doesn't have.
 */
final class PaymentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
