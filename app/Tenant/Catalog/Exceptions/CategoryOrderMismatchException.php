<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a new order doesn't list every subcategory of the parent exactly once.
 */
final class CategoryOrderMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'category_order_mismatch';
    }

    public function fieldErrors(): array
    {
        return ['categories' => [$this->translatedMessage()]];
    }
}
