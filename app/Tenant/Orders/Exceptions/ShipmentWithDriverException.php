<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when staff mark a shipment shipped or delivered while one of the store's drivers has it (Step 17): the driver's delivery moves it along instead.
 */
final class ShipmentWithDriverException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipment_with_driver';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
