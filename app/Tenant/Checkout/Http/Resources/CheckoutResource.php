<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Resources;

use App\Shared\Money\Money;
use App\Shared\Money\MoneyResource;
use App\Tenant\Cart\Data\PricedCart;
use App\Tenant\Cart\Data\PricedCartLine;
use App\Tenant\Checkout\Data\CheckoutIssue;
use App\Tenant\Checkout\Data\CheckoutQuote;
use App\Tenant\Checkout\Data\OfferedPaymentMethod;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Shipping\Data\ShippingOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A checkout and the quote the shopper is shown: what they told us, the lines with their discounts, shipping, tax and total in the quote's currency at the rate it keeps, the ways to pay, and what is still to sort out. Send the version back to place the order.
 *
 * @property-read CheckoutQuote $resource
 */
final class CheckoutResource extends JsonResource
{
    public function __construct(CheckoutQuote $quote)
    {
        parent::__construct($quote);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quote = $this->resource;
        $checkout = $quote->checkout;
        $priced = $quote->priced;
        $currency = $priced->currency->code();
        $locale = app()->getLocale();
        $totals = $priced->basket?->priced;
        $rate = $priced->currency->rate;

        return [
            'id' => $checkout->public_id,
            /** "open"; "placed" once it became an order. */
            'status' => $checkout->status->value,
            /** The quote's version: send it back to place the order. A new version means something charged changed. */
            'version' => $checkout->quote_version,
            /** Until when this version can be placed as shown, its exchange rate included; after it, placing returns a fresh quote. */
            'expires_at' => $checkout->quote_expires_at?->toIso8601String(),
            /** True when it can be placed as it stands; otherwise sort out the issues. */
            'can_be_placed' => $quote->canBePlaced(),
            /**
             * What stops it becoming an order, in the order to sort them out, each with the field to fix when there is one:
             * cart_empty, lines_unavailable, shipping_method_required, shipping_not_available, shipping_rate_unavailable (give the full address),
             * contact_email_required, shipping_address_required, billing_country_required (nothing is sent to you, so tax needs your billing address),
             * buyer_tax_id_required, payment_method_required, payment_method_not_offered, and a module's own.
             *
             * @var list<array{code: string, field: string|null}>
             */
            'issues' => array_map(static fn (CheckoutIssue $issue): array => ['code' => $issue->code, 'field' => $issue->field], $quote->issues),
            'contact' => ['email' => $checkout->contact_email, 'name' => $checkout->contact_name, 'phone' => $checkout->contact_phone],
            'shipping_address' => $checkout->shipping_address,
            /** Null while the billing address is the shipping address. */
            'billing_address' => $checkout->billing_address,
            /** @var array{id: string, country: string|null}|null */
            'buyer_tax_id' => $checkout->buyer_tax_id === null ? null : ['id' => $checkout->buyer_tax_id, 'country' => $checkout->buyer_tax_id_country],
            /** Whether the store requires the buyer's tax ID. */
            'buyer_tax_id_required' => $quote->taxIdRequired,
            /** Whether you agreed to marketing by email and by SMS: only ever true when you said yes. */
            'marketing_consents' => ['email' => $checkout->marketing_email_consent, 'sms' => $checkout->marketing_sms_consent],
            /** The marketing consent wording to show next to each box, never ticked for you. */
            'marketing_consent_wording' => $quote->consentWording,
            'customer_note' => $checkout->customer_note,
            /** The currency every amount is in. */
            'currency' => $currency,
            /** The rate the quote converts the store's prices at (base amount × rate = amount here), kept until it expires; null in the store's own currency. */
            'exchange_rate' => $rate->currency === $rate->baseCurrency ? null : ['base_currency' => $rate->baseCurrency, 'rate' => $rate->rate->toString()],
            /**
             * The cart's lines, as in the cart: lines that can't be bought have no prices and are left out of the totals.
             *
             * @var list<array{id: string, variant: array{id: string, sku: string|null, name: string}, quantity: int, status: string, backorder: bool, unit_price: MoneyResource|null, discount: MoneyResource|null, tax: MoneyResource|null, total: MoneyResource|null}>
             */
            'lines' => array_map(static fn (PricedCartLine $line): array => [
                'id' => $line->line->public_id,
                'variant' => ['id' => $line->line->variant->public_id, 'sku' => $line->line->variant->sku, 'name' => $line->line->variant->product->getTranslation('name', $locale)],
                'quantity' => $line->line->quantity,
                'status' => $line->status->value,
                'backorder' => $line->backorder,
                'unit_price' => $line->priced === null ? null : new MoneyResource($line->priced->price->unitPrice),
                'discount' => $line->priced === null ? null : new MoneyResource($line->priced->discount),
                'tax' => $line->priced === null ? null : new MoneyResource($line->priced->tax->tax),
                'total' => $line->priced === null ? null : new MoneyResource($line->priced->total()),
            ], $priced->lines),
            /** @var list<array{label: string, class: string, from_code: bool, amount: MoneyResource}> */
            'discounts' => $priced->basket === null ? [] : array_map(static fn (DiscountProposal $discount): array => [
                'label' => $discount->label,
                'class' => $discount->class->value,
                'from_code' => $discount->isCode,
                'amount' => new MoneyResource($discount->total($currency)),
            ], $priced->basket->discounts->applied),
            /** @var array{required: bool, options: list<array{id: string, name: string, fulfilment: string, amount: MoneyResource|null, calculated_at_checkout: bool}>, method: string|null, amount: MoneyResource|null, discount: MoneyResource|null} */
            'shipping' => self::shipping($priced, $locale),
            /**
             * What the order comes to. amount_due is what is left to pay once modules' methods (such as a gift card) have covered their part.
             *
             * @var array{subtotal: MoneyResource, discount: MoneyResource, shipping: MoneyResource, tax: MoneyResource, total: MoneyResource, amount_due: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($totals?->subtotal() ?? Money::zero($currency)),
                'discount' => new MoneyResource($totals?->discount() ?? Money::zero($currency)),
                'shipping' => new MoneyResource($totals?->shipping->gross ?? Money::zero($currency)),
                'tax' => new MoneyResource($totals?->tax() ?? Money::zero($currency)),
                'total' => new MoneyResource($totals?->total() ?? Money::zero($currency)),
                'amount_due' => new MoneyResource($totals === null ? Money::zero($currency) : $quote->amountDue()),
            ],
            /**
             * Every way to pay offered. "gateway" and "manual" methods pay what is left (choose one as payment_method); "module" methods (such as a gift card)
             * cover up to an amount and are applied first (module_payment_methods). is_test: a gateway in test mode, which makes the order a test order.
             * payment_due_at: when the order must be paid by with it; null for the usual deadline.
             *
             * @var list<array{key: string, kind: string, covers: MoneyResource|null, is_test: bool, pay_on_delivery: bool, payment_due_at: string|null, instructions: string|null}>
             */
            'payment_methods' => array_map(static fn (OfferedPaymentMethod $method): array => [
                'key' => $method->key,
                'kind' => $method->kind->value,
                'covers' => $method->covers === null ? null : new MoneyResource($method->covers),
                'is_test' => $method->isTest,
                'pay_on_delivery' => $method->payOnDelivery,
                'payment_due_at' => $method->paymentDueAt?->toIso8601String(),
                'instructions' => $method->instructions,
            ], $quote->paymentMethods),
            /** The method chosen to pay what is left; null before one is chosen. */
            'payment_method' => $checkout->payment_method,
            /**
             * Modules' methods applied first, in order. One chosen with nothing left to cover (those before it covered it all) isn't listed: it holds nothing.
             *
             * @var list<string>
             */
            'module_payment_methods' => $quote->chosenModulePaymentMethods(),
        ];
    }

    /**
     * @return array{required: bool, options: list<array{id: string, name: string, fulfilment: string, amount: MoneyResource|null, calculated_at_checkout: bool}>, method: string|null, amount: MoneyResource|null, discount: MoneyResource|null}
     */
    private static function shipping(PricedCart $priced, string $locale): array
    {
        $shipping = $priced->shipping;

        return [
            /** False when nothing ships: no method or shipping address is needed. */
            'required' => $shipping->quote->required,
            /** The ways it can be sent to the address given (or collected). */
            'options' => array_map(static fn (ShippingOption $option): array => [
                'id' => $option->method->public_id,
                'name' => $option->method->getTranslation('name', $locale),
                'fulfilment' => $option->method->fulfilment->value,
                'amount' => $option->amount === null ? null : new MoneyResource($option->amount),
                'calculated_at_checkout' => $option->isCalculatedAtCheckout(),
            ], $shipping->quote->options),
            /** The chosen method; null before one is chosen. */
            'method' => $shipping->chosen?->public_id,
            'amount' => $shipping->option?->amount === null ? null : new MoneyResource($shipping->option->amount),
            'discount' => $shipping->discount === null ? null : new MoneyResource($shipping->discount),
        ];
    }
}
