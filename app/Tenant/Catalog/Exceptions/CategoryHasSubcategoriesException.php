<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when trashing a category that still has subcategories outside the trash; they must be moved or trashed first.
 */
final class CategoryHasSubcategoriesException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_has_subcategories';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
