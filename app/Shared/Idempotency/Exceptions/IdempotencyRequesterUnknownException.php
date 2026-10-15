<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when an Idempotency-Key arrives from nobody known (no sign-in and no guest cart), so the key couldn't be kept to its sender.
 */
final class IdempotencyRequesterUnknownException extends DomainException
{
    public function errorCode(): string
    {
        return 'idempotency_requester_unknown';
    }

    public function status(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
