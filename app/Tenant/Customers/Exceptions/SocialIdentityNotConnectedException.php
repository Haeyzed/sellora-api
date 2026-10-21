<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the provider account just signed in with isn't one connected to the signed-in customer's account.
 */
final class SocialIdentityNotConnectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_identity_not_connected';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
