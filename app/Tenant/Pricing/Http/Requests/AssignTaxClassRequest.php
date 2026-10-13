<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Putting a variant, or every variant of a product, in a tax class. Needs the pricing.manage permission.
 */
final class AssignTaxClassRequest extends FormRequest
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
            /** The tax class's ID, or null for the store's default class (whichever it is at the time). */
            'tax_class' => ['present', 'nullable', 'string', Rule::exists(TaxClass::class, 'public_id')],
        ];
    }

    public function taxClass(): ?TaxClass
    {
        return $this->filled('tax_class') ? TaxClass::query()->where('public_id', $this->string('tax_class')->value())->firstOrFail() : null;
    }
}
