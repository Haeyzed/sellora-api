<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an export is requested while another of the same store is still being built. One at a time per store.
 */
final class StoreExportInProgressException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_export_in_progress';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
