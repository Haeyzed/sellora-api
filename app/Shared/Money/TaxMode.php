<?php

declare(strict_types=1);

namespace App\Shared\Money;

/**
 * Whether a store's prices already include tax, which decides how every stored price is read.
 *
 * Set per store, defaulting from its country at sign-up, and locked once
 * anything is priced (section 3.3).
 */
enum TaxMode: string
{
    /** Prices include tax, as is usual in Europe, Africa and most of the world. */
    case Inclusive = 'inclusive';

    /** Tax is added on top of prices, as is usual in the United States and Canada. */
    case Exclusive = 'exclusive';
}
