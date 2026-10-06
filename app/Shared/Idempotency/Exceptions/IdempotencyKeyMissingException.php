<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a money-related request arrives without a usable Idempotency-Key header, so it can't be safely retried.
 */
final class IdempotencyKeyMissingException extends DomainException
{
    public function errorCode(): string
    {
        return 'idempotency_key_missing';
    }

    /**
     * Reports the problem against the header, like any other invalid input.
     *
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['Idempotency-Key' => [$this->translatedMessage()]];
    }
}
