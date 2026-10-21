<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a customer with a password asks for another way to confirm erasure: they confirm it with their password.
 */
final class ErasureConfirmedByPasswordException extends DomainException
{
    public function errorCode(): string
    {
        return 'erasure_confirmed_by_password';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
