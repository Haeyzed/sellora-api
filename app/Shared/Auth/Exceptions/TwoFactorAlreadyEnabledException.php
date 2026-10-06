<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone starts two-factor setup while it is already on. To move to a new phone, turn it off first.
 */
final class TwoFactorAlreadyEnabledException extends DomainException
{
    public function errorCode(): string
    {
        return 'two_factor_already_enabled';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
