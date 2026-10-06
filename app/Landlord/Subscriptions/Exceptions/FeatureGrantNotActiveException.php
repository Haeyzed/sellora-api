<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when revoking a grant that has already ended.
 */
final class FeatureGrantNotActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'feature_grant_not_active';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
