<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when connecting a provider the customer already has another account of connected: disconnect that one first.
 */
final class SocialProviderAlreadyLinkedException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_provider_already_linked';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
