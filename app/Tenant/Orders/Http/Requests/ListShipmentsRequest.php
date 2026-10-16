<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Enums\ShipmentStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's shipments, newest first, optionally narrowed by status or how they ship. Needs the orders.view permission.
 */
final class ListShipmentsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::enum(ShipmentStatus::class)],
            'fulfilment' => ['sometimes', 'string', Rule::enum(ShippingFulfilment::class)],
        ];
    }

    /**
     * @param  Builder<Shipment>  $query
     */
    public function applyFilters(Builder $query): void
    {
        foreach (['status', 'fulfilment'] as $filter) {
            $value = $this->validated($filter);

            if (is_string($value)) {
                $query->where($filter, $value);
            }
        }
    }
}
