<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a platform admin tries to grant, directly, through a role or through an invitation, platform permissions they don't hold themselves.
 */
final class PlatformPermissionsExceedYourOwnException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_permissions_exceed_your_own';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
