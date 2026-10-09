<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an attribute can't be deleted because a product varies by it, or a variant (even one in the trash) has one of its values.
 */
final class AttributeInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'attribute_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
