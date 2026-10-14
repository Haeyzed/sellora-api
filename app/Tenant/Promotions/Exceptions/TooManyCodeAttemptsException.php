<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when one shopper (account, cart or IP address) enters codes too often: the coupon_redemption limit, per minute.
 */
final class TooManyCodeAttemptsException extends DomainException
{
    public function errorCode(): string
    {
        return 'too_many_code_attempts';
    }

    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
