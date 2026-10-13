<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use InvalidArgumentException;

/**
 * One line to price: a variant, how many, and any discount on the line, such as a cart line.
 */
final readonly class PricingLine
{
    /**
     * @param  string  $key  Identifies the line in the result, such as a cart line's ID.
     * @param  Money|null  $discount  Off the whole line, in the quote's currency (Promotions works it out and spreads order discounts by largest remainder); at most the line's price.
     *
     * @throws InvalidArgumentException When the quantity is below one or the discount is negative.
     */
    public function __construct(
        public string $key,
        public ProductVariant $variant,
        public int $quantity,
        public ?Money $discount = null,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException("The quantity of line \"{$key}\" must be at least 1.");
        }

        if ($discount?->isNegative() === true) {
            throw new InvalidArgumentException("The discount of line \"{$key}\" can't be negative.");
        }
    }
}
