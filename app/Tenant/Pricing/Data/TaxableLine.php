<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Data;

use App\Shared\Money\Money;
use App\Tenant\Pricing\Enums\TaxLineKind;
use InvalidArgumentException;

/**
 * One line to be taxed: its amount after discounts, read through the store's tax mode, and its tax class.
 *
 * In a tax-inclusive store the amount already includes tax; in a
 * tax-exclusive store tax is added to it.
 */
final readonly class TaxableLine
{
    /**
     * @param  string  $key  Identifies the line in the result, such as a cart line's ID.
     * @param  Money  $amount  Unit price × quantity − discounts, never negative.
     *
     * @throws InvalidArgumentException When the amount is negative or the quantity is below one.
     */
    public function __construct(
        public string $key,
        public TaxLineKind $kind,
        public Money $amount,
        public TaxClassData $taxClass,
        public int $quantity = 1,
    ) {
        if ($amount->isNegative()) {
            throw new InvalidArgumentException("The amount of line \"{$key}\" can't be negative.");
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException("The quantity of line \"{$key}\" must be at least 1.");
        }
    }
}
