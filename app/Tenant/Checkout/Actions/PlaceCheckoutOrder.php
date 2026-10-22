<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Actions;

use App\Shared\Exceptions\DomainException;
use App\Shared\Money\Money;
use App\Tenant\Cart\Actions\ClearOrderedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Cart\Data\Shopper;
use App\Tenant\Cart\Enums\CartLineStatus;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Checkout\Data\CheckoutQuote;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Checkout\Data\PlacedCheckout;
use App\Tenant\Checkout\Enums\CheckoutStatus;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Enums\PaymentMethodKind;
use App\Tenant\Checkout\Exceptions\CheckoutNotFoundException;
use App\Tenant\Checkout\Exceptions\CheckoutNotReadyException;
use App\Tenant\Checkout\Exceptions\CheckoutQuoteChangedException;
use App\Tenant\Checkout\Exceptions\GuestCheckoutNotAllowedException;
use App\Tenant\Checkout\Exceptions\QuoteNoLongerStands;
use App\Tenant\Checkout\Exceptions\ReturnUrlRequiredException;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Checkout\PaymentMethods\GatewayPaymentMethods;
use App\Tenant\Checkout\PaymentMethods\ManualPaymentMethods;
use App\Tenant\Checkout\Services\CheckoutQuotes;
use App\Tenant\Customers\Actions\RecordMarketingConsent;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Orders\Actions\IssueOrderAccessToken;
use App\Tenant\Orders\Actions\PlaceOrder;
use App\Tenant\Orders\Data\BuyerTaxId;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Data\OrderDraft;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Actions\StartPayment;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Promotions\Actions\ReservePromotionUses;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Exceptions\PromotionUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Places the order for a checkout whose quote the shopper confirmed (Step 16), all or nothing.
 *
 * In one transaction, always in this order, so two checkouts never wait for
 * each other the other way round: the checkout (an advisory lock and its
 * row), the cart's row, the quote checked again (the version shown, not
 * expired, everything charged the same at the rate it keeps), the stock, the
 * promotion uses, the order (PlaceOrder, which gives it the store's next
 * number), each chosen payment method's hold, the marketing consent the
 * shopper gave, a guest's order token, and the cart emptied. When stock or a
 * promotion use ran out meanwhile, or anything charged changed, nothing is
 * placed and the new quote is saved and returned (409). The gateway is only
 * called once all of it is committed: a slow or failed gateway never holds
 * locks, and never undoes the order, which can be paid again. Placing a
 * checkout that already became an order returns that order.
 */
final readonly class PlaceCheckoutOrder
{
    public function __construct(
        private FindCheckout $findCheckout,
        private CheckoutQuotes $checkoutQuotes,
        private ReserveStock $reserveStock,
        private ReservePromotionUses $reservePromotionUses,
        private PlaceOrder $placeOrder,
        private StartManualPayment $startManualPayment,
        private StartPayment $startPayment,
        private RecordMarketingConsent $recordMarketingConsent,
        private IssueOrderAccessToken $issueOrderAccessToken,
        private ClearOrderedCart $clearOrderedCart,
    ) {}

    /**
     * @param  int  $version  The quote version the shopper confirmed.
     * @param  string|null  $returnUrl  Where a gateway sends the buyer back; needed when paying on a gateway's page.
     * @param  string  $idempotencyKey  The client's, for the gateway payment: a retry returns the same payment.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws CheckoutNotFoundException
     * @throws CheckoutQuoteChangedException With the new quote, when it isn't the one confirmed.
     * @throws CheckoutNotReadyException With the quote, when it still has issues.
     * @throws ReturnUrlRequiredException
     */
    public function handle(Shopper $shopper, string $checkoutId, int $version, ?string $returnUrl, string $idempotencyKey): PlacedCheckout
    {
        $checkout = $this->findCheckout->byId($shopper, $checkoutId);

        try {
            $placed = DB::transaction(fn (): PlacedCheckout => $this->place($checkout, $version, $returnUrl));
        } catch (QuoteNoLongerStands) {
            // Outside the transaction that was undone, so the new quote is kept.
            throw new CheckoutQuoteChangedException($this->checkoutQuotes->refresh($checkout->refresh()));
        }

        return $this->startGatewayPayment($placed, $returnUrl, $idempotencyKey);
    }

    /**
     * @throws QuoteNoLongerStands
     * @throws CheckoutNotReadyException
     * @throws ReturnUrlRequiredException
     */
    private function place(Checkout $checkout, int $version, ?string $returnUrl): PlacedCheckout
    {
        // 1. The checkout: a second press waits here, then finds the first's order.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['order-checkout:'.$checkout->public_id]);
        $checkout = Checkout::query()->whereKey($checkout->id)->lockForUpdate()->firstOrFail();

        if ($checkout->status === CheckoutStatus::Placed) {
            return $this->alreadyPlaced($checkout);
        }

        // 2. The cart, as the quote was priced from it: the lock every change to it takes, so none slips in until the order is placed.
        $cart = Cart::query()->whereKey($checkout->cart_id)->lock('for no key update')->firstOrFail();
        $checkout->setRelation('cart', $cart);

        // 3. The quote, as it stands now at the rate it keeps.
        $quote = $this->checkoutQuotes->current($checkout);

        if (! $this->checkoutQuotes->stands($checkout, $version) || $quote->hash !== $checkout->quote_hash || $cart->version !== $checkout->cart_version) {
            throw new QuoteNoLongerStands;
        }

        if (! $quote->canBePlaced()) {
            throw new CheckoutNotReadyException($quote);
        }

        $chosen = $quote->paymentMethod;

        if ($chosen?->kind === PaymentMethodKind::Gateway && $returnUrl === null) {
            throw new ReturnUrlRequiredException;
        }

        $basket = $quote->priced->basket ?? throw new LogicException('A quote with no issues has something to buy.');
        $buyer = $checkout->customer === null ? DiscountBuyer::guest($checkout->contact_email) : DiscountBuyer::customer($checkout->customer);
        $holdUntil = CarbonImmutable::now()->addMinutes(config()->integer('checkout.quote_expire_minutes'));

        // 4. The stock, then 5. the promotion uses: either ran out meanwhile, and the quote no longer stands.
        try {
            $this->reserveStock->handle(StockHolder::of($checkout), $this->stockLines($quote), $holdUntil);
            $this->reservePromotionUses->handle(new RedemptionHolder($checkout->getMorphClass(), $checkout->public_id), $basket->discounts, $buyer, $holdUntil);
        } catch (InsufficientStockException|PromotionUnavailableException) {
            throw new QuoteNoLongerStands;
        }

        // 6. The order, numbered.
        $order = $this->placeOrder->handle($this->draft($checkout, $quote, $chosen))->order;

        // 7. Each chosen payment method holds its part: modules' first, then core's for what is left.
        $this->hold($quote, $order);

        if ($chosen?->kind === PaymentMethodKind::Manual) {
            $this->startManualPayment->handle($order, ManualPaymentMethods::methodOf($chosen->key) ?? throw new LogicException("\"{$chosen->key}\" isn't paid by hand."));
        }

        // 8. Marketing consent, only what the shopper said yes to.
        $this->recordConsents($checkout);

        // 9. A guest's way back to their order.
        $orderToken = $order->customer_id === null ? $this->issueOrderAccessToken->handle($order) : null;

        $checkout->forceFill(['status' => CheckoutStatus::Placed, 'order_id' => $order->id, 'placed_at' => CarbonImmutable::now(), 'last_activity_at' => CarbonImmutable::now()])->save();

        // 10. The cart is done with.
        $this->clearOrderedCart->handle($cart);

        return new PlacedCheckout($checkout, $order, true, Payment::query()->where('order_id', $order->id)->latest('id')->first(), $chosen, $orderToken, null);
    }

    private function alreadyPlaced(Checkout $checkout): PlacedCheckout
    {
        $order = Order::query()->findOrFail($checkout->order_id);
        $chosen = null;

        if ($checkout->payment_method !== null) {
            $chosen = GatewayPaymentMethods::gatewayOf($checkout->payment_method) !== null
                ? new OfferedPaymentMethod($checkout->payment_method, PaymentMethodKind::Gateway, isTest: $order->is_test)
                : new OfferedPaymentMethod($checkout->payment_method, PaymentMethodKind::Manual, payOnDelivery: $order->pay_on_delivery);
        }

        return new PlacedCheckout($checkout, $order, false, Payment::query()->where('order_id', $order->id)->latest('id')->first(), $chosen, null, null);
    }

    /**
     * @return list<StockLine>
     */
    private function stockLines(CheckoutQuote $quote): array
    {
        $lines = array_filter($quote->priced->lines, static fn (PricedCartLine $line): bool => $line->status === CartLineStatus::Available);

        return array_values(array_map(static fn (PricedCartLine $line): StockLine => new StockLine($line->line->variant, $line->line->quantity), $lines));
    }

    private function draft(Checkout $checkout, CheckoutQuote $quote, ?OfferedPaymentMethod $chosen): OrderDraft
    {
        $basket = $quote->priced->basket ?? throw new LogicException('A quote with no issues has something to buy.');
        $email = (string) $checkout->contact_email;
        $taxId = $checkout->buyer_tax_id === null ? null : new BuyerTaxId($checkout->buyer_tax_id, (string) $checkout->buyer_tax_id_country);
        $shippingAddress = $checkout->shipping_address === null ? null : AddressSnapshot::fromArray($checkout->shipping_address);
        $billingAddress = $checkout->billing_address === null ? $shippingAddress : AddressSnapshot::fromArray($checkout->billing_address);
        $option = $quote->priced->shipping->quote->required ? $quote->priced->shipping->option : null;

        return new OrderDraft(
            $checkout->public_id,
            $basket,
            $checkout->customer === null
                ? OrderBuyer::guest($email, $checkout->contact_name, $checkout->contact_phone, $taxId)
                : OrderBuyer::customer($checkout->customer, $email, $checkout->contact_name, $checkout->contact_phone, $taxId),
            $billingAddress,
            $option !== null && $option->method->fulfilment->needsAddress() ? $shippingAddress : null,
            $option,
            StockHolder::of($checkout),
            new RedemptionHolder($checkout->getMorphClass(), $checkout->public_id),
            $checkout->locale,
            $checkout->customer_note,
            $chosen?->paymentDueAt,
            $chosen->isTest ?? false,
            $chosen->payOnDelivery ?? false,
        );
    }

    /**
     * Asks each chosen method to hold its part, and records what it held. A module that can no longer hold what the quote showed means the quote no longer stands.
     *
     * @throws QuoteNoLongerStands
     */
    private function hold(CheckoutQuote $quote, Order $order): void
    {
        foreach ($quote->moduleCovers as $key => $covered) {
            [$offer, $method] = $quote->offer($key);
            $held = $method->hold($offer, $order, $covered);

            if (! $held->amount->isEqualTo($covered)) {
                throw new QuoteNoLongerStands;
            }

            $this->recordHold($order, $key, $held->amount, $held->reference);
        }

        if ($quote->paymentMethod !== null) {
            [$offer, $method] = $quote->offer($quote->paymentMethod->key);
            $held = $method->hold($offer, $order, $quote->amountDue());
            $this->recordHold($order, $offer->key, $held->amount, $held->reference);
        }
    }

    private function recordHold(Order $order, string $key, Money $amount, ?string $reference): void
    {
        $hold = new CheckoutPaymentHold;
        $hold->forceFill([
            'order_id' => $order->id,
            'method' => $key,
            'reference' => $reference,
            'amount' => $amount,
            'status' => PaymentHoldStatus::Held,
        ])->save();
    }

    private function recordConsents(Checkout $checkout): void
    {
        $consents = [
            [MarketingChannel::Email, $checkout->marketing_email_consent, $checkout->contact_email],
            // A yes to SMS needs a phone number to mean anything.
            [MarketingChannel::Sms, $checkout->marketing_sms_consent, $checkout->contact_phone],
        ];

        foreach ($consents as [$channel, $said, $contact]) {
            if ($said && $contact !== null) {
                $this->recordMarketingConsent->handle($channel, $contact, MarketingConsentStatus::Granted, MarketingConsentSource::Checkout, $checkout->locale, $checkout->customer);
            }
        }
    }

    /**
     * Sends the buyer to the gateway's page for what is left, now that the order is committed. A gateway that refuses or doesn't answer leaves the order standing, to be paid again.
     */
    private function startGatewayPayment(PlacedCheckout $placed, ?string $returnUrl, string $idempotencyKey): PlacedCheckout
    {
        $gateway = $placed->paymentMethod === null ? null : GatewayPaymentMethods::gatewayOf($placed->paymentMethod->key);

        if ($gateway === null || $returnUrl === null || ! $placed->order->refresh()->awaitsPayment()) {
            return $placed;
        }

        try {
            $payment = $this->startPayment->handle($placed->order, $gateway, $returnUrl, $idempotencyKey);
        } catch (DomainException $exception) {
            return new PlacedCheckout($placed->checkout, $placed->order, $placed->isNew, null, $placed->paymentMethod, $placed->orderToken, $exception->errorCode());
        }

        return new PlacedCheckout($placed->checkout, $placed->order, $placed->isNew, $payment, $placed->paymentMethod, $placed->orderToken, null);
    }
}
