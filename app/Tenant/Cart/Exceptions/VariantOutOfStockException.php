<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a variant is out of stock and doesn't take backorders. Never says how many are left (section 14).
 */
final class VariantOutOfStockException extends DomainException
{
    public function errorCode(): string
    {
        return 'variant_out_of_stock';
    }

    public function fieldErrors(): array
    {
        return ['variant' => [$this->translatedMessage()]];
    }
}
