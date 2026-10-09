<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Raised when a variant wouldn't have exactly one value for each of its product's options, or a product's variants have changed since the request listed them.
 */
final class ProductOptionsIncompleteException extends DomainException
{
    /**
     * @param  string  $field  The input field holding the values.
     */
    public function __construct(private readonly string $field = 'values', ?Throwable $previous = null)
    {
        parent::__construct(previous: $previous);
    }

    public function errorCode(): string
    {
        return 'product_options_incomplete';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
