<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another category outside the trash already uses the slug.
 */
final class CategorySlugTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_slug_taken';
    }

    public function fieldErrors(): array
    {
        return ['slug' => [$this->translatedMessage()]];
    }
}
