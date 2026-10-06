<?php

declare(strict_types=1);

namespace App\Landlord\Subscriptions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when starting a subscription for a store that already has one; its plan must be changed instead.
 */
final class SubscriptionAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'subscription_already_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
