<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a store already has an active grant for the feature; revoke it first to change it.
 */
final class FeatureAlreadyGrantedException extends DomainException
{
    public function errorCode(): string
    {
        return 'feature_already_granted';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
