<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone resends or cancels an invitation that was already accepted or cancelled.
 */
final class StaffInvitationNotPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'staff_invitation_not_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
