<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a customer asks for a verification link for an email they have already confirmed.
 */
final class CustomerEmailAlreadyVerifiedException extends DomainException
{
    public function errorCode(): string
    {
        return 'customer_email_already_verified';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
