<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Checkout\Data\PlacedCheckout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order a checkout became, and how to pay what is left: send the buyer to redirect_url for a gateway, or show the instructions for a method paid by hand.
 *
 * @property-read PlacedCheckout $resource
 */
final class PlacedOrderResource extends JsonResource
{
    public function __construct(PlacedCheckout $placed)
    {
        parent::__construct($placed);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $placed = $this->resource;
        $order = $placed->order;
        $payment = $placed->payment;
        $method = $placed->paymentMethod;

        return [
            'checkout' => $placed->checkout->public_id,
            'order' => [
                'id' => $order->public_id,
                'number' => $order->displayNumber(),
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                /** A test order, paid with a gateway in test mode: never fulfilled for real. */
                'is_test' => $order->is_test,
                'total' => new MoneyResource($order->total),
                /** When it must be paid by, or it is cancelled and its stock released. */
                'payment_due_at' => $order->payment_due_at->toIso8601String(),
            ],
            /** The method chosen to pay what is left; null when modules' methods covered it all. */
            'payment_method' => $method === null ? null : [
                'key' => $method->key,
                'kind' => $method->kind->value,
                'pay_on_delivery' => $method->payOnDelivery,
                'instructions' => $method->instructions,
            ],
            /** The payment started for what is left; null when there is nothing to pay now, or the gateway couldn't start one (see payment_error). */
            'payment' => $payment === null ? null : [
                'reference' => $payment->reference,
                'status' => $payment->status->value,
                'amount' => new MoneyResource($payment->amount),
                /** The gateway's page to send the buyer to; null for a method paid by hand. */
                'redirect_url' => $payment->redirect_url,
            ],
            /** Why the gateway payment couldn't start, such as payment_gateway_unreachable; the order stands and can be paid again. */
            'payment_error' => $placed->paymentError,
        ];
    }
}
