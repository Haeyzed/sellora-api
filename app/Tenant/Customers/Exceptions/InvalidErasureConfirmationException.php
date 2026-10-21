<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the token confirming erasure isn't one of the signed-in customer's, was used already, or has expired. Ask for a new one.
 */
final class InvalidErasureConfirmationException extends DomainException
{
    public function errorCode(): string
    {
        return 'erasure_confirmation_invalid';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
