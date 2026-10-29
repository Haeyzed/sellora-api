<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A page of the store's returns, newest first, optionally narrowed by status or order. Needs the returns.view permission.
 */
final class ListReturnsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', OrderReturn::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::enum(ReturnStatus::class)],
            /** An order's ID. */
            'order' => ['sometimes', 'string', 'max:26'],
        ];
    }

    /**
     * @param  Builder<OrderReturn>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $status = $this->validated('status');

        if (is_string($status)) {
            $query->where('status', $status);
        }

        $order = $this->validated('order');

        if (is_string($order)) {
            $query->whereIn('order_id', Order::query()->where('public_id', $order)->select('id'));
        }
    }
}
