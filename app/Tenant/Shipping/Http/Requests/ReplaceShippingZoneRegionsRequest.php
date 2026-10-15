<?php

declare(strict_types=1);

namespace App\Tenant\Shipping\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Shipping\Models\ShippingZone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Setting the countries and states a zone covers, replacing its list. Needs the shipping.manage permission.
 */
final class ReplaceShippingZoneRegionsRequest extends FormRequest
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
        return $this->regionRules(required: true);
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [$this->regionsListedOnce()];
    }
}
