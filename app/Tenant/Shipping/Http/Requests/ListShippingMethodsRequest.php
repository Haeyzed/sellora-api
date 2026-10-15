<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Validation\Rule;

/**
 * A page of the store's shipping methods, optionally one zone's or one kind's; archived ones only when asked for. Needs the shipping.view permission.
 */
final class ListShippingMethodsRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', ShippingZone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only this zone's methods, by its ID. */
            'zone' => ['sometimes', 'string', Rule::exists(ShippingZone::class, 'public_id')],
            /** Only methods of this kind: "ship", "own_delivery" or "pickup". */
            'fulfilment' => ['sometimes', Rule::enum(ShippingFulfilment::class)],
            /** True for archived methods only; false (the default) for the others. */
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    public function zoneId(): ?int
    {
        return $this->filled('zone') ? (int) ShippingZone::query()->where('public_id', $this->string('zone')->value())->value('id') : null;
    }

    public function fulfilment(): ?ShippingFulfilment
    {
        return $this->filled('fulfilment') ? ShippingFulfilment::from($this->string('fulfilment')->value()) : null;
    }

    public function archived(): bool
    {
        return $this->boolean('archived');
    }
}
