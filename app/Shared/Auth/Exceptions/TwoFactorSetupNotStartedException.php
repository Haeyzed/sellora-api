<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone confirms two-factor setup without having started it, or after it is already confirmed.
 */
final class TwoFactorSetupNotStartedException extends DomainException
{
    public function errorCode(): string
    {
        return 'two_factor_setup_not_started';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
