<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an export doesn't exist, belongs to another store, or was requested by someone else. Only its requester can see or download it.
 */
final class StoreExportNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_export_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
