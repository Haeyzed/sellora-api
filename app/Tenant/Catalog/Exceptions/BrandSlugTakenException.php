<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another brand outside the trash already uses the slug.
 */
final class BrandSlugTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'brand_slug_taken';
    }

    public function fieldErrors(): array
    {
        return ['slug' => [$this->translatedMessage()]];
    }
}
