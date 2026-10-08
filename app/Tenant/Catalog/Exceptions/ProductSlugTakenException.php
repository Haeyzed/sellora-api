<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another product outside the trash already uses the slug.
 */
final class ProductSlugTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_slug_taken';
    }

    public function fieldErrors(): array
    {
        return ['slug' => [$this->translatedMessage()]];
    }
}
