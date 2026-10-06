<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an export is downloaded before it is ready, after it failed, or after it expired.
 */
final class StoreExportNotReadyException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_export_not_ready';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
