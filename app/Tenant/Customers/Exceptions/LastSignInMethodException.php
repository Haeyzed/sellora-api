<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when disconnecting a provider would leave the account with no way to sign in. Set a password (through a password reset) or connect another provider first.
 */
final class LastSignInMethodException extends DomainException
{
    public function errorCode(): string
    {
        return 'last_sign_in_method';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
