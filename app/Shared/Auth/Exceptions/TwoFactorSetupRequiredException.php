<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an account that must use two-factor authentication tries to do anything before setting it up.
 */
final class TwoFactorSetupRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'two_factor_setup_required';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
