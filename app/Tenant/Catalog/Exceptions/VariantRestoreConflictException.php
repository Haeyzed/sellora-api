<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a variant can't come back from the trash: another variant now has its SKU or its combination of values, or its values no longer match the product's options.
 */
final class VariantRestoreConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'variant_restore_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
