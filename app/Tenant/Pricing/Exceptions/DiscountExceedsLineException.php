<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Raised when a line's discount is more than the line itself: a line never costs less than nothing.
 */
final class DiscountExceedsLineException extends DomainException
{
    public function __construct(string $lineKey)
    {
        parent::__construct(['line' => $lineKey]);
    }

    public function errorCode(): string
    {
        return 'discount_exceeds_line';
    }
}
