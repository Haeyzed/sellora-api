<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a new code is requested for a sign-up that has already become a store.
 */
final class StoreRegistrationAlreadyVerifiedException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_registration_already_verified';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
