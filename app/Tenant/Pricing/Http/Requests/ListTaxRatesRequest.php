<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Validation\Rule;

/**
 * A page of the store's tax rates, optionally one country's or one class's. Needs the pricing.view permission.
 */
final class ListTaxRatesRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', TaxClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only this country's rates, such as "NG". */
            'country' => ['sometimes', 'string', 'regex:/^[A-Z]{2}$/'],
            /** Only this tax class's rates, by its ID. */
            'tax_class' => ['sometimes', 'string', Rule::exists(TaxClass::class, 'public_id')],
        ];
    }

    public function countryCode(): ?string
    {
        return $this->filled('country') ? $this->string('country')->value() : null;
    }

    public function taxClassId(): ?int
    {
        return $this->filled('tax_class') ? (int) TaxClass::query()->where('public_id', $this->string('tax_class')->value())->value('id') : null;
    }
}
