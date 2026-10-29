<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a return can't move to the status asked for from where it stands, such as cancelling one already received, or when its buyer tries what only staff may do.
 */
final class ReturnTransitionNotAllowedException extends DomainException
{
    public function errorCode(): string
    {
        return 'return_transition_not_allowed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
