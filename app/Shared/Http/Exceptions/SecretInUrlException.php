<?php

declare(strict_types=1);

namespace App\Shared\Http\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a request carries a token, password, code or credential in its URL, which is refused rather than read: secrets travel in a header or the request body.
 */
final class SecretInUrlException extends DomainException
{
    public function errorCode(): string
    {
        return 'secret_in_url';
    }

    /**
     * A malformed request rather than invalid input.
     */
    public function status(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
