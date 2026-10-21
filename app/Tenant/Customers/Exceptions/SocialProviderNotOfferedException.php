<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when signing in with a provider the store doesn't offer, or the platform hasn't set up.
 */
final class SocialProviderNotOfferedException extends DomainException
{
    public function errorCode(): string
    {
        return 'social_provider_not_offered';
    }
}
