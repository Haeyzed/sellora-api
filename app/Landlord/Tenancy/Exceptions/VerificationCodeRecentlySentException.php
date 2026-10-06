<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a new code is requested less than a minute after the last one.
 */
final class VerificationCodeRecentlySentException extends DomainException
{
    public function errorCode(): string
    {
        return 'verification_code_recently_sent';
    }

    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
