<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Raised when another variant outside the trash already uses the SKU.
 */
final class VariantSkuTakenException extends DomainException
{
    /**
     * @param  string  $field  The input field the SKU was sent in, such as "sku" or "variant.sku".
     */
    public function __construct(private readonly string $field = 'sku', ?Throwable $previous = null)
    {
        parent::__construct(previous: $previous);
    }

    public function errorCode(): string
    {
        return 'variant_sku_taken';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
