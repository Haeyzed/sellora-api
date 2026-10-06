<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an account's sign-in is paused after too many wrong attempts.
 */
final class SignInTemporarilyLockedException extends DomainException
{
    public function __construct(public readonly int $secondsUntilUnlocked)
    {
        parent::__construct(['minutes' => max(1, (int) ceil($secondsUntilUnlocked / 60))]);
    }

    public function errorCode(): string
    {
        return 'sign_in_temporarily_locked';
    }

    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
