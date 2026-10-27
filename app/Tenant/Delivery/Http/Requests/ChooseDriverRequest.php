<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Giving a shipment, or a delivery not yet picked up, to a driver. Needs the delivery.manage permission.
 */
final class ChooseDriverRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Delivery::class);
    }

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return [
            /** The driver's ID. They must be active. */
            'driver' => ['required', 'string', 'max:64', Rule::exists(Driver::class, 'public_id')],
        ];
    }

    public function chosenDriver(): Driver
    {
        return Driver::query()->where('public_id', $this->string('driver')->toString())->firstOrFail();
    }
}
