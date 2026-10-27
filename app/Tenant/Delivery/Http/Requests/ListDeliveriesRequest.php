<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Delivery\Enums\DeliveryStatus;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * A page of the store's deliveries, newest first, optionally narrowed by status or driver. Needs the delivery.view permission.
 */
final class ListDeliveriesRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Delivery::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::enum(DeliveryStatus::class)],
            /** A driver's ID. */
            'driver' => ['sometimes', 'string', 'max:64'],
            /** Only deliveries still under way (true) or only finished ones (false). */
            'open' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Builder<Delivery>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $status = $this->validated('status');

        if (is_string($status)) {
            $query->where('status', $status);
        }

        $driver = $this->validated('driver');

        if (is_string($driver)) {
            $query->whereIn('driver_id', Driver::query()->where('public_id', $driver)->select('id'));
        }

        if ($this->has('open')) {
            $this->boolean('open') ? $query->open() : $query->whereNot(static fn (Builder $closed) => $closed->open());
        }
    }
}
