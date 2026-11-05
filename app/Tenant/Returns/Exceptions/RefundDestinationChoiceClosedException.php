<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when where a return's refund goes can't be chosen now: the return is finished, or a refund attempt may be giving money back (one in progress, unknown, in staff review or partly done). The choice reopens only once the last attempt definitely got nothing back.
 */
final class RefundDestinationChoiceClosedException extends DomainException
{
    public function errorCode(): string
    {
        return 'refund_destination_choice_closed';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
