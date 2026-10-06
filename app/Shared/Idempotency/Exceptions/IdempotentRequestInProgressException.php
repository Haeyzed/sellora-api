<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a duplicate arrives while the original request is still running, so the two can never both complete.
 */
final class IdempotentRequestInProgressException extends DomainException
{
    public function errorCode(): string
    {
        return 'idempotent_request_in_progress';
    }

    /**
     * A conflict: the client should wait and retry, after which it gets the original answer.
     */
    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
