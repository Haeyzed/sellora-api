<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when the last variant outside the trash would be moved to the trash: every product has at least one variant. Trash the product instead.
 */
final class ProductNeedsAVariantException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_needs_a_variant';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
