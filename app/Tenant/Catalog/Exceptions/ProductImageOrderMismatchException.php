<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a new gallery order doesn't list every image of the product exactly once.
 */
final class ProductImageOrderMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'product_image_order_mismatch';
    }

    public function fieldErrors(): array
    {
        return ['images' => [$this->translatedMessage()]];
    }
}
