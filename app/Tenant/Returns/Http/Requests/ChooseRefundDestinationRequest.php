<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A signed-in customer choosing where one of their returns' refund goes. Anyone else's return is not found (404), never forbidden.
 */
final class ChooseRefundDestinationRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /** Where it goes: "original_payment", or a key from GET /customer/returns/{return}/refund-destinations. */
            'destination' => ['required', 'string', 'max:64'],
        ];
    }

    public function destination(): string
    {
        return $this->string('destination')->value();
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
