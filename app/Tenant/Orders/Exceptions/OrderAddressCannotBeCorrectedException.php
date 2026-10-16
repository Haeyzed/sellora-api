<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when correcting the shipping address of an order that isn't open, has nothing shipped to an address, or already has a shipment.
 */
final class OrderAddressCannotBeCorrectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'order_address_cannot_be_corrected';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
