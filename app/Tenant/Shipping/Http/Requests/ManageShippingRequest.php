<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Shipping\Models\ShippingZone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Removing or retiring part of the store's shipping setup. Needs the shipping.manage permission.
 */
final class ManageShippingRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', ShippingZone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
