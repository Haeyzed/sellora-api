<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Shipping\Models\ShippingZone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Adding a shipping zone, or changing one (send only what changes; its regions change on their own). Needs the shipping.manage permission.
 */
final class SaveShippingZoneRequest extends FormRequest
{
    use ActsAsStaffMember;
    use ValidatesShippingRegions;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', ShippingZone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->zone() === null;

        return [
            /** For staff, such as "West Africa"; unique, ignoring case. */
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            /** Whether it ships; an inactive zone's regions get no shipping (they don't fall through to another zone). Active when left out. */
            'is_active' => ['sometimes', 'boolean:strict'],
            /** Whether it covers every country no zone lists. Only one zone can, and it lists no regions. */
            'covers_rest_of_world' => ['sometimes', 'boolean:strict'],
            ...($creating ? $this->regionRules(required: false) : ['regions' => ['missing']]),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [$this->regionsListedOnce()];
    }

    public function zoneName(): ?string
    {
        return $this->has('name') ? trim($this->string('name')->value()) : null;
    }

    public function isActive(): ?bool
    {
        return $this->has('is_active') ? $this->boolean('is_active') : null;
    }

    public function coversRestOfWorld(): ?bool
    {
        return $this->has('covers_rest_of_world') ? $this->boolean('covers_rest_of_world') : null;
    }

    private function zone(): ?ShippingZone
    {
        $zone = $this->route('zone');

        return $zone instanceof ShippingZone ? $zone : null;
    }
}
