<?php

declare(strict_types=1);

namespace App\Shared\Auth\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone signs in correctly to an account that has been deactivated. Only shown after the right password or PIN.
 */
final class AccountDeactivatedException extends DomainException
{
    public function errorCode(): string
    {
        return 'account_deactivated';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
