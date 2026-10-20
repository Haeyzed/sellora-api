<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the money recorded isn't a positive amount in the payment's currency.
 */
final class PaymentReceiptInvalidException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_receipt_invalid';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
