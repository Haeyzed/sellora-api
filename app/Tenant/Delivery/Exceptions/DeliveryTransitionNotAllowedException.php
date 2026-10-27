<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a delivery can't move to that status from where it is, such as delivering one that was never picked up, or failing one already delivered.
 */
final class DeliveryTransitionNotAllowedException extends DomainException
{
    public function errorCode(): string
    {
        return 'delivery_transition_not_allowed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
