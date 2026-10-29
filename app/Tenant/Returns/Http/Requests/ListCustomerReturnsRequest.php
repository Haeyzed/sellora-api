<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\Builder;

/**
 * A page of the signed-in customer's own returns, of every order of theirs, newest first.
 */
final class ListCustomerReturnsRequest extends CursorListRequest
{
    use ActsAsCustomer;

    /**
     * The customer's returns, of every order of theirs.
     *
     * @return Builder<OrderReturn>
     */
    public function ownReturns(): Builder
    {
        return OrderReturn::query()->whereHas('order', fn (Builder $orders) => $orders->where('customer_id', $this->customer()->id));
    }
}
