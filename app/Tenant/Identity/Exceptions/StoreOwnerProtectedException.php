<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when someone tries to change the store owner's roles or deactivate them. Ownership only moves by an ownership transfer.
 */
final class StoreOwnerProtectedException extends DomainException
{
    public function errorCode(): string
    {
        return 'store_owner_protected';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
