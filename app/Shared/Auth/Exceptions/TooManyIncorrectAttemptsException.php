<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a signed-in person has entered their current password wrong too many times.
 */
final class TooManyIncorrectAttemptsException extends DomainException
{
    public function __construct(public readonly int $secondsUntilUnlocked)
    {
        parent::__construct(['minutes' => max(1, (int) ceil($secondsUntilUnlocked / 60))]);
    }

    public function errorCode(): string
    {
        return 'too_many_incorrect_attempts';
    }

    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
