<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when deactivating or erasing a driver who still has goods: a delivery picked up, on the way, or failed and not yet resolved. Resolve those first.
 */
final class DriverHoldsGoodsException extends DomainException
{
    public function errorCode(): string
    {
        return 'driver_holds_goods';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
