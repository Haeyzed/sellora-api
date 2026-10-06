<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when an invitation link is unknown, expired, cancelled or already used. The same error for each, so it reveals nothing.
 */
final class InvalidPlatformAdminInvitationException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_admin_invitation_invalid';
    }
}
