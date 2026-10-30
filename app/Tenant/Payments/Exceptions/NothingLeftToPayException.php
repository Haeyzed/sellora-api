<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when starting a payment on an order with nothing left to pay, though it still counts as waiting for payment: a payment is never started for zero or less.
 */
final class NothingLeftToPayException extends DomainException
{
    public function errorCode(): string
    {
        return 'nothing_left_to_pay';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
