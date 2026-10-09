<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a product can't come back from the trash because another product now uses its slug, or another variant one of its variants' SKUs.
 */
final class ProductRestoreConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_restore_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
