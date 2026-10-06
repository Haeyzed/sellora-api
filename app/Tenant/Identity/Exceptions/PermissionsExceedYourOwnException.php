<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone tries to grant, through a role or an invitation, permissions they don't hold themselves, or to manage someone with more permissions than they have.
 */
final class PermissionsExceedYourOwnException extends DomainException
{
    public function errorCode(): string
    {
        return 'permissions_exceed_your_own';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
