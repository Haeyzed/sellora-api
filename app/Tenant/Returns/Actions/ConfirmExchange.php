<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Checkout\Actions\PlaceReplacementOrder;
use App\Tenant\Checkout\Exceptions\ReplacementCannotBePlacedException;
use App\Tenant\Checkout\Exceptions\ReplacementQuoteChangedException;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Enums\ReturnExchangeStatus;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\ReturnExchangeNotReadyException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnExchange;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Shipping\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;

/**
 * Staff confirming a return's exchange as it was previewed (Step 18d): its replacement order is placed through Checkout, with the returned items' credit held on it, and the return waits for it to be paid.
 *
 * In one transaction, the return's lock first: worked out again, it must
 * come to what staff were shown (the quote's hash), and the replacement's
 * stock and promotion uses are held as the order is placed. The exchange
 * is saved with how the returned items settle it, and the return moves to
 * awaiting_exchange (ReturnExchangeConfirmed). When the credit covers the
 * replacement, it is paid at once and settles once this commits.
 * Confirming again returns the same exchange.
 */
final readonly class ConfirmExchange
{
    public function __construct(
        private ReturnProgress $returnProgress,
        private PreviewExchange $previewExchange,
        private PlaceReplacementOrder $placeReplacementOrder,
    ) {}

    /**
     * @param  string  $quoteHash  The hash of the preview staff confirmed.
     * @param  ShippingMethod|null  $method  How to send it, as previewed; null for the way the order was sent.
     *
     * @throws ReturnExchangeNotReadyException
     * @throws ReplacementQuoteChangedException With the new quote, when it no longer comes to what was shown.
     * @throws ReplacementCannotBePlacedException When a replacement is out of stock or no longer for sale, the currency isn't offered, or the delivery can't be priced.
     */
    public function handle(OrderReturn $return, StaffMember $actor, string $quoteHash, ?ShippingMethod $method = null): ReturnExchange
    {
        return DB::transaction(function () use ($return, $actor, $quoteHash, $method): ReturnExchange {
            $locked = $this->returnProgress->lock($return);
            $live = $locked->liveExchange();

            if ($live !== null) {
                return $live;
            }

            $preview = $this->previewExchange->handle($locked, $method);
            // Placing refuses a quote that isn't the one confirmed: the one check of the hash.
            $placed = $this->placeReplacementOrder->handle($preview->terms->request, $quoteHash);
            $by = AccountReference::to($actor);
            $quote = $preview->quote;

            $exchange = new ReturnExchange;
            $exchange->forceFill([
                'return_id' => $locked->id,
                'order_id' => $placed->order->id,
                'status' => ReturnExchangeStatus::Pending,
                'stores_fault' => $preview->terms->storesFault,
                'shipping_charged' => $preview->terms->request->chargesShipping && $quote->shipping !== null,
                'currency' => $quote->returnedValue->currency(),
                'returned_value' => $quote->returnedValue,
                'credit' => $quote->credit,
                'difference' => $preview->difference,
                'kept' => $preview->kept,
                'confirmed_by_type' => $by->type,
                'confirmed_by_id' => $by->publicId,
            ])->save();

            $this->returnProgress->move($locked, ReturnStatus::AwaitingExchange, $by);

            activity('returns')
                ->causedBy($actor)
                ->performedOn($locked)
                ->event('return_exchange_confirmed')
                ->withProperties(['exchange' => $exchange->public_id, 'order' => $placed->order->public_id])
                ->log('Confirmed the exchange of return '.$locked->displayNumber().' with order '.$placed->order->displayNumber());

            return $exchange;
        });
    }
}
