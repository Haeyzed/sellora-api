<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a customer who hasn't confirmed their email asks for something that needs it, such as downloading their data (section 10).
 */
final class CustomerEmailNotVerifiedException extends DomainException
{
    public function errorCode(): string
    {
        return 'customer_email_not_verified';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
