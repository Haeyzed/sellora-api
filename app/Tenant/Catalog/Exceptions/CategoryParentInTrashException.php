<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when restoring a category whose parent is in the trash; the parent must be restored first.
 */
final class CategoryParentInTrashException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_parent_in_trash';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
