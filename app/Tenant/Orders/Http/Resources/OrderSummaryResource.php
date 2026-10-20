<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order in a list: who, how much, and where it stands. Show it for its lines and everything else.
 *
 * @property Order $resource
 */
final class OrderSummaryResource extends JsonResource
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

        return [
            'id' => $order->public_id,
            /** For people to read, such as 1042 (shown "#1042"). */
            'number' => $order->number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            /** Placed with a test-mode payment method: only test payments pay it, and it is never a sale. */
            'is_test' => $order->is_test,
            /** Paid in cash on delivery: it may ship before it is paid. */
            'pay_on_delivery' => $order->pay_on_delivery,
            'fulfilment_status' => $order->fulfilment_status->value,
            'refund_due' => $order->refund_due,
            'customer' => $order->customer === null ? null : ['id' => $order->customer->public_id],
            /**
             * The buyer's contact details as they stand.
             */
            'contact' => ['email' => $contact->email, 'name' => $contact->name],
            'total' => new MoneyResource($order->total),
            /** @var list<string> */
            'tags' => array_values($order->tags->pluck('tag')->sort()->values()->all()),
            'placed_at' => $order->placed_at->toIso8601String(),
        ];
    }
}
