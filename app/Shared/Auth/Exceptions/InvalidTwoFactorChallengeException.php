<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a two-factor challenge token is unknown, expired or already answered, so the person must sign in with their password again.
 */
final class InvalidTwoFactorChallengeException extends DomainException
{
    public function errorCode(): string
    {
        return 'two_factor_challenge_invalid';
    }
}
