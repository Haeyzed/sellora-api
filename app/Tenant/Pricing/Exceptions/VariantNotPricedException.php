<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when pricing a variant that has no price yet: it can't be bought (section 3.3).
 */
final class VariantNotPricedException extends DomainException
{
    public function __construct(string $variantId)
    {
        parent::__construct(['variant' => $variantId]);
    }

    public function errorCode(): string
    {
        return 'variant_not_priced';
    }
}
