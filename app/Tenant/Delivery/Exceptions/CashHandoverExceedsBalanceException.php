<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when recording a cash handover larger than what the driver holds in that currency.
 */
final class CashHandoverExceedsBalanceException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_handover_exceeds_balance';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
