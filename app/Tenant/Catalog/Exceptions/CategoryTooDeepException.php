<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a category would sit deeper in the tree than the store allows.
 */
final class CategoryTooDeepException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_too_deep';
    }

    public function fieldErrors(): array
    {
        return ['parent' => [$this->translatedMessage()]];
    }
}
