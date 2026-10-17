<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when starting a payment while another attempt on the order may still go through. Finish or wait for that one first.
 */
final class PaymentInProgressException extends DomainException
{
    public function errorCode(): string
    {
        return 'payment_in_progress';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
