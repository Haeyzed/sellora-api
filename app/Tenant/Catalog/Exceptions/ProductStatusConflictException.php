<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a product can't make the change asked for from where it is now, such as archiving a product that is already archived.
 */
final class ProductStatusConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_status_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
