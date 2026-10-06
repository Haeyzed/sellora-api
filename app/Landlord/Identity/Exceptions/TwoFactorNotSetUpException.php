<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when resetting two-factor authentication for an admin who hasn't set it up.
 */
final class TwoFactorNotSetUpException extends DomainException
{
    public function errorCode(): string
    {
        return 'two_factor_not_enabled';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
