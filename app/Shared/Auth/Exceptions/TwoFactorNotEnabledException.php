<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an action needs two-factor authentication to be on (new recovery codes, turning it off), but it isn't.
 */
final class TwoFactorNotEnabledException extends DomainException
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
