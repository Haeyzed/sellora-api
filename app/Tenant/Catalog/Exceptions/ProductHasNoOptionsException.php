<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when a variant is added to a product that has no options yet: a product without options, such as Size, has exactly one variant.
 */
final class ProductHasNoOptionsException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_has_no_options';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
