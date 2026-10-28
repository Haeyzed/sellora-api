<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A signed-in customer following the store's own deliveries of one of their orders. Anyone else's order is not found (404), never forbidden.
 */
final class CustomerDeliveryTrackingRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @throws ModelNotFoundException When the order isn't the customer's.
     */
    public function ownOrder(Order $order): Order
    {
        if ($order->customer_id !== $this->customer()->id) {
            throw (new ModelNotFoundException)->setModel(Order::class, [$order->public_id]);
        }

        return $order;
    }
}
