<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Tenant\Orders\Concerns\ActsAsGuestOrderHolder;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest looking at or calling off one of their order's returns, with the order's access token in the X-Order-Token header. Without it, or for another order's return, it is not found (404).
 */
final class ViewGuestReturnRequest extends FormRequest
{
    use ActsAsGuestOrderHolder;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @throws ModelNotFoundException When the request doesn't carry the order's token, or the return isn't the order's.
     */
    public function guestReturn(Order $order, OrderReturn $return): OrderReturn
    {
        $order = $this->guestOrder($order);

        if ($return->order_id !== $order->id) {
            throw (new ModelNotFoundException)->setModel(OrderReturn::class, [$return->public_id]);
        }

        return $return;
    }
}
