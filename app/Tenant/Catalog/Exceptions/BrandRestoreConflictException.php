<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a brand can't come back from the trash because another brand now uses its slug.
 */
final class BrandRestoreConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'brand_restore_conflict';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
