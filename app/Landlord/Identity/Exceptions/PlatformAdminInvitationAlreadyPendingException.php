<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when inviting someone who already has a pending invitation; it can be resent instead.
 */
final class PlatformAdminInvitationAlreadyPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'platform_admin_invitation_already_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
