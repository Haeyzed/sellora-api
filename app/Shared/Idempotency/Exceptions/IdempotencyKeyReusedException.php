<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a client reuses an Idempotency-Key for a different request, which would otherwise replay the wrong answer.
 */
final class IdempotencyKeyReusedException extends DomainException
{
    public function errorCode(): string
    {
        return 'idempotency_key_reused';
    }
}
