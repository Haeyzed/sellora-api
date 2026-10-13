<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding a tax class. Needs the pricing.manage permission.
 */
final class StoreTaxClassRequest extends FormRequest
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
            /** Unique in the store, ignoring case, such as "Children's clothing". */
            'name' => ['required', 'string', 'max:100'],
            /** True for things outside tax altogether; zero-rated things are a normal class with 0% rates. */
            'is_exempt' => ['sometimes', 'boolean:strict'],
        ];
    }

    public function taxClassName(): string
    {
        return trim($this->string('name')->value());
    }

    public function isExempt(): bool
    {
        return $this->boolean('is_exempt');
    }
}
