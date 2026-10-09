<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an attribute value can't be deleted because a variant, even one in the trash, has it.
 */
final class AttributeValueInUseException extends DomainException
{
    public function errorCode(): string
    {
        return 'attribute_value_in_use';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
