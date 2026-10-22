<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

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
     * A malformed request rather than invalid input: the header every money-related request must carry is missing.
     */
    public function status(): int
    {
        return Response::HTTP_BAD_REQUEST;
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
