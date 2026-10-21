<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when connecting a provider account that is already connected to another customer at this store.
 */
final class SocialIdentityInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_identity_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
