<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Privacy;

use App\Shared\Auth\AccountReference;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\OrderAddressKind;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Services\OrderAnonymisation;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the customer and guest privacy handlers share: a person's orders as their export shows them, and erasing them.
 *
 * Erasing runs inside the registry's PersonalDataErasure transaction: an
 * order still waiting for payment is cancelled (its stock and promotion uses
 * go back) and anonymised; a settled one is anonymised; one paid for and
 * still being fulfilled, or owing a refund, keeps what it needs until it
 * settles, when the daily purge anonymises it.
 */
final readonly class OrderPrivacy
{
    public function __construct(
        private CancelOrder $cancelOrder,
        private OrderAnonymisation $orderAnonymisation,
    ) {}

    /**
     * @param  Builder<Order>  $orders
     * @return list<array<string, mixed>>
     */
    public function export(Builder $orders): array
    {
        return array_values($orders->with(['lines', 'addresses', 'contactCorrections'])->orderBy('id')->get()->map(static function (Order $order): array {
            $contact = $order->currentContact();

            return [
                'number' => $order->number,
                'placed_at' => $order->placed_at->toIso8601String(),
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'fulfilment_status' => $order->fulfilment_status->value,
                'contact' => ['email' => $contact->email, 'name' => $contact->name, 'phone' => $contact->phone],
                'shipping_address' => $order->currentAddress(OrderAddressKind::Shipping)?->details(),
                'billing_address' => $order->currentAddress(OrderAddressKind::Billing)?->details(),
                'note' => $order->customer_note,
                'lines' => array_values($order->lines->map(static fn (OrderLine $line): array => [
                    'product' => $line->product_name,
                    'variant' => $line->variant_name,
                    'quantity' => $line->quantity,
                    'total' => $line->total->format('en'),
                ])->all()),
                'total' => $order->total->format('en'),
            ];
        })->all());
    }

    /**
     * Erases what the orders hold about the person, inside the registry's erasure.
     *
     * @param  Builder<Order>  $orders
     */
    public function erase(Builder $orders, ?AccountReference $person): void
    {
        foreach ($orders->whereNull('anonymised_at')->orderBy('id')->get() as $order) {
            if ($order->awaitsPayment()) {
                $order = $this->cancelOrder->forErasure($order, $person);
            }

            if ($this->orderAnonymisation->isSettled($order)) {
                $this->orderAnonymisation->anonymise($order);
            } else {
                $order->forceFill(['anonymise_when_settled' => true])->save();
            }
        }
    }
}
