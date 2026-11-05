<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Tenant\Checkout\Data\PlacedReplacement;
use App\Tenant\Checkout\Data\ReplacementLine;
use App\Tenant\Checkout\Data\ReplacementQuote;
use App\Tenant\Checkout\Data\ReplacementRequest;
use App\Tenant\Checkout\Exceptions\ReplacementCannotBePlacedException;
use App\Tenant\Checkout\Exceptions\ReplacementQuoteChangedException;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\PaymentMethods\ExchangeCreditPaymentMethod;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Actions\PlaceOrder;
use App\Tenant\Orders\Data\BuyerTaxId;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Data\OrderDraft;
use App\Tenant\Orders\Data\OrderSource;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RecalculateOrderPayments;
use App\Tenant\Promotions\Actions\ReservePromotionUses;
use App\Tenant\Promotions\Exceptions\PromotionUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Places the replacement order for a return in an exchange (Step 18c), as staff confirmed it from its quote, all or nothing.
 *
 * In one transaction, in checkout's own order: the return's lock, the quote
 * worked out again (refused when it no longer comes to what staff were
 * shown, or can't be placed now), the stock, the promotion uses, the order
 * (PlaceOrder, numbered, replacing the return, with the original order's
 * test flag), and the exchange credit held on it. When the credit covers it
 * all, the order is paid at once, and the credit is committed with the
 * order's other holds. Placing again for a return whose replacement isn't
 * cancelled returns that one.
 */
final readonly class PlaceReplacementOrder
{
    public function __construct(
        private QuoteReplacementOrder $quoteReplacementOrder,
        private ReserveStock $reserveStock,
        private ReservePromotionUses $reservePromotionUses,
        private PlaceOrder $placeOrder,
        private RecordPaymentHold $recordPaymentHold,
        private RecalculateOrderPayments $recalculateOrderPayments,
    ) {}

    /**
     * @param  string  $quoteHash  The hash of the quote staff confirmed.
     *
     * @throws ReplacementQuoteChangedException With the new quote, when it isn't the one confirmed.
     * @throws ReplacementCannotBePlacedException With the quote, when it can't be placed now.
     */
    public function handle(ReplacementRequest $request, string $quoteHash): PlacedReplacement
    {
        $source = OrderSource::replacingReturn($request->returnId, $request->returnPublicId);

        return DB::transaction(function () use ($request, $quoteHash, $source): PlacedReplacement {
            // A second confirmation waits here, then finds the first's order.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [$source->lockKey()]);

            $existing = Order::query()->where('replaces_return_id', $request->returnId)->where('status', '<>', OrderStatus::Cancelled)->first();

            if ($existing !== null) {
                return new PlacedReplacement($existing, false, $this->creditOf($existing));
            }

            $quote = $this->quoteReplacementOrder->handle($request);

            if (! $quote->canBePlaced()) {
                throw new ReplacementCannotBePlacedException($quote);
            }

            if ($quote->hash !== $quoteHash) {
                throw new ReplacementQuoteChangedException($quote);
            }

            $this->reserve($request, $quote);
            $order = $this->placeOrder->handle($this->draft($request, $quote, $source))->order;
            $credit = $quote->credit->isPositive()
                ? $this->recordPaymentHold->handle($order, ExchangeCreditPaymentMethod::KEY, $quote->credit, $request->returnPublicId)
                : null;

            // The returned items covered it all: nothing is left to wait for, so it is paid now.
            if ($quote->amountDue->isZero()) {
                $order = $this->recalculateOrderPayments->handle($order);
            }

            return new PlacedReplacement($order, true, $credit);
        });
    }

    /**
     * The stock and the promotion uses, held by the return until the order takes them over: either running out meanwhile means it can't be placed as quoted.
     *
     * @throws ReplacementCannotBePlacedException
     * @throws ReplacementQuoteChangedException
     */
    private function reserve(ReplacementRequest $request, ReplacementQuote $quote): void
    {
        $basket = $quote->basket ?? throw new LogicException('A quote that can be placed is priced.');
        $holdUntil = CarbonImmutable::now()->addMinutes(config()->integer('checkout.quote_expire_minutes'));

        try {
            $this->reserveStock->handle(
                $request->stockHolder(),
                array_map(static fn (ReplacementLine $line): StockLine => new StockLine($line->variant, $line->quantity), $request->lines),
                $holdUntil,
            );
        } catch (InsufficientStockException) {
            throw new ReplacementCannotBePlacedException(new ReplacementQuote(null, null, $quote->returnedValue, $quote->total, $quote->credit, $quote->amountDue, $quote->difference, [QuoteReplacementOrder::OUT_OF_STOCK], $quote->hash));
        }

        try {
            $this->reservePromotionUses->handle($request->redemptionHolder(), $basket->discounts, $request->discountBuyer(), $holdUntil);
        } catch (PromotionUnavailableException) {
            throw new ReplacementQuoteChangedException($quote);
        }
    }

    private function draft(ReplacementRequest $request, ReplacementQuote $quote, OrderSource $source): OrderDraft
    {
        $original = $request->original;
        $basket = $quote->basket ?? throw new LogicException('A quote that can be placed is priced.');
        $contact = $original->loadMissing('contactCorrections')->currentContact();
        $taxId = $original->buyer_tax_id === null ? null : new BuyerTaxId($original->buyer_tax_id, (string) $original->buyer_tax_id_country);
        $email = (string) $contact->email;
        $shippingAddress = $request->shippingAddress();
        $billingAddress = $request->billingAddress();

        return new OrderDraft(
            $source,
            $basket,
            $original->customer === null
                ? OrderBuyer::guest($email, $contact->name, $contact->phone, $taxId)
                : OrderBuyer::customer($original->customer, $email, $contact->name, $contact->phone, $taxId),
            $billingAddress,
            $quote->shipping !== null && $quote->shipping->method->fulfilment->needsAddress() ? $shippingAddress : null,
            $quote->shipping,
            $request->stockHolder(),
            $request->redemptionHolder(),
            $original->locale,
            isTest: $original->is_test,
        );
    }

    private function creditOf(Order $order): ?CheckoutPaymentHold
    {
        return CheckoutPaymentHold::query()->where('order_id', $order->id)->where('method', ExchangeCreditPaymentMethod::KEY)->first();
    }
}
