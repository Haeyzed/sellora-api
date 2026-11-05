<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Tenant\Checkout\Actions\QuoteReplacementOrder;
use App\Tenant\Returns\Data\ExchangePreview;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnExchangeNotReadyException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Services\ReturnExchangeTerms;
use App\Tenant\Shipping\Models\ShippingMethod;

/**
 * What a return's exchange would come to if staff confirmed it now (Step 18d): the replacement order priced as Checkout prices it, what the returned items credit toward it, what the buyer still owes, what goes back to them and what the store keeps, with a hash to confirm it by.
 *
 * Reading changes nothing and holds nothing: prices, promotions, stock and
 * rates are checked again when it is confirmed.
 */
final readonly class PreviewExchange
{
    public function __construct(
        private ReturnExchangeTerms $returnExchangeTerms,
        private QuoteReplacementOrder $quoteReplacementOrder,
    ) {}

    /**
     * @param  ShippingMethod|null  $method  How to send it; null for the way the order was sent.
     *
     * @throws ReturnExchangeNotReadyException When it isn't an exchange ready to confirm.
     */
    public function handle(OrderReturn $return, ?ShippingMethod $method = null): ExchangePreview
    {
        if (! self::isReady($return)) {
            throw new ReturnExchangeNotReadyException;
        }

        $terms = $this->returnExchangeTerms->for($return, $method);
        $quote = $this->quoteReplacementOrder->handle($terms->request);
        ['difference' => $difference, 'kept' => $kept] = $terms->settle($quote);

        return new ExchangePreview($quote, $terms, $difference, $kept);
    }

    /**
     * Whether a return's exchange can be confirmed: an exchange, inspected or approved with the buyer keeping the items.
     */
    public static function isReady(OrderReturn $return): bool
    {
        return $return->resolution === ReturnResolution::Exchange
            && ($return->status === ReturnStatus::Inspected || ($return->status === ReturnStatus::Approved && $return->keep_items));
    }
}
