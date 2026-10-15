<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when changing an archived shipping method: it stays as orders chose it. Add a new method instead.
 */
final class ShippingMethodArchivedException extends DomainException
{
    public function errorCode(): string
    {
        return 'shipping_method_archived';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
