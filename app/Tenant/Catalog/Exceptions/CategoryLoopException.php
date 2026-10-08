<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a category would move under itself or one of its own subcategories.
 */
final class CategoryLoopException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_loop';
    }

    public function fieldErrors(): array
    {
        return ['parent' => [$this->translatedMessage()]];
    }
}
