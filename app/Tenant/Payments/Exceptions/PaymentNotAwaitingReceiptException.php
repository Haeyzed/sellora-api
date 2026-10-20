<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when money is recorded for a payment that isn't paid by hand, or no longer waits for money (already received, failed or in review).
 */
final class PaymentNotAwaitingReceiptException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_not_awaiting_receipt';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
