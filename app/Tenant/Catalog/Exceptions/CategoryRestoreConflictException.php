<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a category can't come back from the trash because another category now uses its slug.
 */
final class CategoryRestoreConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_restore_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
