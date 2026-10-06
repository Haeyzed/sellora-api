<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when resending or cancelling an invitation that was already accepted or cancelled.
 */
final class PlatformAdminInvitationNotPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_admin_invitation_not_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
