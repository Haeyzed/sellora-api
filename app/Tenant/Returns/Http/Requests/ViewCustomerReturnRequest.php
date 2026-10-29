<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A signed-in customer looking at or calling off one of their returns. Anyone else's return is not found (404), never forbidden.
 */
final class ViewCustomerReturnRequest extends FormRequest
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
     * @throws ModelNotFoundException When the return isn't of one of the customer's orders.
     */
    public function ownReturn(OrderReturn $return): OrderReturn
    {
        if ($return->order->customer_id !== $this->customer()->id) {
            throw (new ModelNotFoundException)->setModel(OrderReturn::class, [$return->public_id]);
        }

        return $return;
    }
}
