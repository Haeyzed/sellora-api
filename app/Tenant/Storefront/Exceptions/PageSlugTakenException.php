<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when another page outside the trash already uses the slug.
 */
final class PageSlugTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'page_slug_taken';
    }

    public function fieldErrors(): array
    {
        return ['slug' => [$this->translatedMessage()]];
    }
}
