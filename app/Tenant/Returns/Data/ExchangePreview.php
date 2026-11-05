<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Data;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Data\ReplacementQuote;

/**
 * A return's exchange as it would be confirmed now (Step 18d): the replacement order's quote, and how the returned items settle it.
 */
final readonly class ExchangePreview
{
    /**
     * @param  Money  $difference  What goes back to the buyer, to the destination they chose.
     * @param  Money  $kept  What the store keeps: what a free like-for-like replacement didn't use.
     */
    public function __construct(
        public ReplacementQuote $quote,
        public ExchangeTerms $terms,
        public Money $difference,
        public Money $kept,
    ) {}
}
