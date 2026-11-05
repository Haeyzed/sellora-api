<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Data;

use App\Shared\Money\Money;

/**
 * Fixed reductions on a replacement order in an exchange (Step 18c): what is taken off each line and the delivery, after promotions, so the buyer pays what the exchange agreed.
 *
 * Only replacement orders give them: never a cart, a checkout or anything a
 * shopper sends (an architecture rule enforces it). Each is taken off what
 * the line or delivery still costs after its promotions, never more, and is
 * recorded on the order as its own discount, from the source "exchange".
 */
final readonly class PriceAllowances
{
    public const string SOURCE = 'exchange';

    /**
     * @param  string  $reference  What the allowance is for, such as the return's public ID.
     * @param  string  $label  What the buyer sees, in their language.
     * @param  array<string, Money>  $lines  By line key; only lines that get one.
     * @param  Money|null  $shipping  Off the delivery; null for none.
     */
    public function __construct(
        public string $reference,
        public string $label,
        public array $lines,
        public ?Money $shipping,
    ) {}
}
