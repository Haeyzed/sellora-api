<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAddress;
use App\Tenant\Orders\Models\OrderDiscount;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\OrderTax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as staff see it: its statuses, the buyer, every line with its discounts and taxes, the shipping, the addresses as they stand, and the totals, all as placed.
 *
 * @property Order $resource
 */
final class OrderResource extends JsonResource
{
    public function __construct(Order $order)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $contact = $order->currentContact();
        $shipping = $order->shippingLine;

        return [
            'id' => $order->public_id,
            /** For people to read, such as 1042 (shown "#1042"); never used to find an order. */
            'number' => $order->number,
            /** "open", "completed" or "cancelled". */
            'status' => $order->status->value,
            /** "unpaid" or "paid". */
            'payment_status' => $order->payment_status->value,
            /** "unfulfilled", "partially_fulfilled", "fulfilled", or "not_required" when nothing ships. */
            'fulfilment_status' => $order->fulfilment_status->value,
            /** True for a cancelled order whose payment is owed back. */
            'refund_due' => $order->refund_due,
            /** The account it belongs to; null for a guest order. */
            'customer' => $order->customer === null ? null : ['id' => $order->customer->public_id],
            /**
             * The buyer's contact details as they stand: staff's latest correction, or as given at checkout.
             */
            'contact' => ['email' => $contact->email, 'name' => $contact->name, 'phone' => $contact->phone, 'is_corrected' => $contact->isCorrected],
            /** The language its names were copied in. */
            'locale' => $order->locale,
            'currency' => $order->currency,
            /**
             * @var array{subtotal: MoneyResource, discount: MoneyResource, shipping: MoneyResource, shipping_discount: MoneyResource, tax: MoneyResource, total: MoneyResource}
             */
            'totals' => [
                'subtotal' => new MoneyResource($order->subtotal),
                'discount' => new MoneyResource($order->discount),
                /** Before free-shipping discounts. */
                'shipping' => new MoneyResource($order->shipping),
                'shipping_discount' => new MoneyResource($order->shipping_discount),
                'tax' => new MoneyResource($order->tax),
                'total' => new MoneyResource($order->total),
            ],
            /**
             * The rate it was priced at (base amount × rate = amount in its currency), and its total in the base currency.
             *
             * @var array{base_currency: string, rate: string, source: string, as_of: string|null, base_total: MoneyResource}
             */
            'exchange_rate' => [
                'base_currency' => $order->base_currency,
                'rate' => $order->exchange_rate->toString(),
                'source' => $order->rate_source->value,
                'as_of' => $order->rate_as_of?->toIso8601String(),
                'base_total' => new MoneyResource($order->base_total),
            ],
            'tax' => ['mode' => $order->tax_mode->value, 'country_code' => $order->tax_country_code, 'state_code' => $order->tax_state_code],
            /**
             * @var list<array{id: string, product: array{id: string, name: string}, variant: array{id: string, name: string|null, sku: string|null}, quantity: int, requires_shipping: bool, unit_price: MoneyResource, subtotal: MoneyResource, discount: MoneyResource, tax: MoneyResource, total: MoneyResource, discounts: list<array{label: string, class: string, code: string|null, amount: MoneyResource}>, taxes: list<array{name: string, rate_percent: string, amount: MoneyResource}>}>
             */
            'lines' => array_values($order->lines->map(static fn (OrderLine $line): array => [
                'id' => $line->public_id,
                'product' => ['id' => $line->product->public_id, 'name' => $line->product_name],
                'variant' => ['id' => $line->variant->public_id, 'name' => $line->variant_name, 'sku' => $line->sku],
                'quantity' => $line->quantity,
                'requires_shipping' => $line->requires_shipping,
                'unit_price' => new MoneyResource($line->unit_price),
                'subtotal' => new MoneyResource($line->subtotal),
                'discount' => new MoneyResource($line->discount),
                'tax' => new MoneyResource($line->tax),
                'total' => new MoneyResource($line->total),
                'discounts' => array_values($line->discounts->map(self::discount(...))->all()),
                'taxes' => array_values($line->taxes->map(self::tax(...))->all()),
            ])->all()),
            /**
             * How it ships and what that cost, as chosen; null when nothing ships.
             *
             * @var array{method: array{id: string, name: string}, fulfilment: string, carrier: string|null, carrier_service: string|null, pickup_address: array{line1: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string}|null, price: MoneyResource, discount: MoneyResource, tax: MoneyResource, total: MoneyResource, estimate: array{min_days: int, max_days: int, earliest: string, latest: string}|null, discounts: list<array{label: string, class: string, code: string|null, amount: MoneyResource}>, taxes: list<array{name: string, rate_percent: string, amount: MoneyResource}>}|null
             */
            'shipping' => $shipping === null ? null : [
                'method' => ['id' => $shipping->method->public_id, 'name' => $shipping->method_name],
                'fulfilment' => $shipping->fulfilment->value,
                'carrier' => $shipping->carrier,
                'carrier_service' => $shipping->carrier_service,
                'pickup_address' => $shipping->pickup_country_code === null ? null : ['line1' => $shipping->pickup_address_line1, 'city' => $shipping->pickup_city, 'state_code' => $shipping->pickup_state_code, 'postal_code' => $shipping->pickup_postal_code, 'country_code' => $shipping->pickup_country_code],
                'price' => new MoneyResource($shipping->price),
                'discount' => new MoneyResource($shipping->discount),
                'tax' => new MoneyResource($shipping->tax),
                'total' => new MoneyResource($shipping->total),
                'estimate' => $shipping->min_days === null || $shipping->max_days === null || $shipping->earliest_on === null || $shipping->latest_on === null ? null : ['min_days' => $shipping->min_days, 'max_days' => $shipping->max_days, 'earliest' => $shipping->earliest_on->toDateString(), 'latest' => $shipping->latest_on->toDateString()],
                'discounts' => array_values($order->discounts->whereNull('order_line_id')->map(self::discount(...))->all()),
                'taxes' => array_values($order->taxes->whereNull('order_line_id')->map(self::tax(...))->all()),
            ],
            /**
             * Where it ships to as it stands: staff's latest correction, or as placed; null when nothing ships to an address.
             */
            'shipping_address' => self::address($order->currentAddress(OrderAddressKind::Shipping)),
            'billing_address' => self::address($order->currentAddress(OrderAddressKind::Billing)),
            /** What the buyer wrote at checkout. */
            'customer_note' => $order->customer_note,
            /** @var list<string> */
            'tags' => array_values($order->tags->pluck('tag')->sort()->values()->all()),
            'placed_at' => $order->placed_at->toIso8601String(),
            /** Cancelled if still unpaid after this. */
            'payment_due_at' => $order->payment_due_at->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            /** "staff", "customer" or "payment_expired"; null unless cancelled. */
            'cancellation_reason' => $order->cancellation_reason?->value,
        ];
    }

    /**
     * @return array{label: string, class: string, code: string|null, amount: MoneyResource}
     */
    private static function discount(OrderDiscount $discount): array
    {
        return ['label' => $discount->label, 'class' => $discount->class->value, 'code' => $discount->code, 'amount' => new MoneyResource($discount->discount)];
    }

    /**
     * @return array{name: string, rate_percent: string, amount: MoneyResource}
     */
    private static function tax(OrderTax $tax): array
    {
        return ['name' => $tax->name, 'rate_percent' => $tax->rate_percent->toString(), 'amount' => new MoneyResource($tax->tax)];
    }

    /**
     * @return array{recipient_name: string|null, company: string|null, line1: string|null, line2: string|null, city: string|null, state_code: string|null, postal_code: string|null, country_code: string, phone: string|null, version: int}|null
     */
    private static function address(?OrderAddress $address): ?array
    {
        return $address === null ? null : [...$address->details(), 'version' => $address->version];
    }
}
