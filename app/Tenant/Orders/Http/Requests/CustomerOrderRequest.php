<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A signed-in customer looking at or cancelling one of their own orders. Anyone else's is not found (404), never forbidden, so trying IDs reveals nothing.
 */
final class CustomerOrderRequest extends FormRequest
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
