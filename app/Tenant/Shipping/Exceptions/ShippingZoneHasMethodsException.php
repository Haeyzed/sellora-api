<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when deleting a zone that still has methods that aren't archived. Archive or move them first.
 */
final class ShippingZoneHasMethodsException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipping_zone_has_methods';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
