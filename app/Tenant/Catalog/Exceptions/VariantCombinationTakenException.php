<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Exceptions;

use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Raised when another variant of the product outside the trash already has the same combination of values, such as two "Blue, M" shirts.
 */
final class VariantCombinationTakenException extends DomainException
{
    /**
     * @param  string  $field  The input field the values were sent in.
     */
    public function __construct(private readonly string $field = 'values', ?Throwable $previous = null)
    {
        parent::__construct(previous: $previous);
    }

    public function errorCode(): string
    {
        return 'variant_combination_taken';
    }

    public function fieldErrors(): array
    {
        return [$this->field => [$this->translatedMessage()]];
    }
}
