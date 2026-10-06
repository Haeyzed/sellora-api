<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone invites an email that already has a pending invitation. Resend that invitation instead.
 */
final class StaffInvitationAlreadyPendingException extends DomainException
{
    public function errorCode(): string
    {
        return 'staff_invitation_already_pending';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
