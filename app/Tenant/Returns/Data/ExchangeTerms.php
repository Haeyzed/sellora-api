<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Data;

use App\Shared\Money\Money;
use App\Tenant\Checkout\Data\ReplacementQuote;
use App\Tenant\Checkout\Data\ReplacementRequest;

/**
 * How a return's exchange would be settled now (Step 18d): the replacement order to ask Checkout for, and which of its lines are free like-for-like.
 */
final readonly class ExchangeTerms
{
    /**
     * @param  array<int, Money>  $lineValues  What each returned line's units are worth, by position in the request.
     * @param  list<int>  $freeLines  Positions of the lines sent free like-for-like: the store was at fault and the replacement is the same product at the same price.
     */
    public function __construct(
        public ReplacementRequest $request,
        public array $lineValues,
        public array $freeLines,
        public bool $storesFault,
    ) {}

    /**
     * What of the returned items' value the replacement doesn't use, split between what goes back to the buyer and what the store keeps: a free line's unused value is kept (the difference is zero whatever today's price), the rest goes back.
     *
     * @return array{difference: Money, kept: Money}
     */
    public function settle(ReplacementQuote $quote): array
    {
        $unused = $quote->difference;
        $currency = $unused->currency();
        $freeUnused = Money::zero($currency);

        foreach ($this->freeLines as $position) {
            $total = $quote->lineTotal($position);
            $left = $total === null ? Money::zero($currency) : $this->lineValues[$position]->subtract($total);

            if ($left->isPositive()) {
                $freeUnused = $freeUnused->add($left);
            }
        }

        $kept = $freeUnused->isGreaterThan($unused) ? $unused : $freeUnused;

        return ['difference' => $unused->subtract($kept), 'kept' => $kept];
    }
}
