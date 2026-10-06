<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when inviting someone who already has a platform admin account.
 */
final class PlatformAdminAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_admin_already_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
