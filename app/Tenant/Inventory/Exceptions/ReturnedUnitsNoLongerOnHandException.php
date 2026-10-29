<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when units put back on the shelf from a return can't be sent back to the buyer, because fewer are free there now (sold or held since).
 */
final class ReturnedUnitsNoLongerOnHandException extends DomainException
{
    public function errorCode(): string
    {
        return 'returned_units_no_longer_on_hand';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
