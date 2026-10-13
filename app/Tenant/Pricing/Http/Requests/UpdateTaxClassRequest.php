<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Data\TaxClassChanges;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changing a tax class. Needs the pricing.manage permission. Send only what changes.
 */
final class UpdateTaxClassRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', TaxClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            /** True for things outside tax altogether. */
            'is_exempt' => ['sometimes', 'boolean:strict'],
            /** Only true: makes it the default class in place of the current one. To change the default, make another class the default. */
            'is_default' => ['sometimes', 'boolean:strict', Rule::in([true])],
            /** True to tax shipping at this class, in place of any other; false to stop taxing shipping. */
            'used_for_shipping' => ['sometimes', 'boolean:strict'],
        ];
    }

    public function changes(): TaxClassChanges
    {
        return new TaxClassChanges(
            name: $this->has('name') ? trim($this->string('name')->value()) : null,
            isExempt: $this->has('is_exempt') ? $this->boolean('is_exempt') : null,
            makeDefault: $this->boolean('is_default'),
            usedForShipping: $this->has('used_for_shipping') ? $this->boolean('used_for_shipping') : null,
        );
    }
}
