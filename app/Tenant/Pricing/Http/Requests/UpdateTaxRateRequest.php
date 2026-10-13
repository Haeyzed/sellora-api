<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changing a rate's name or percentage. Needs the pricing.manage permission. Send only what changes.
 */
final class UpdateTaxRateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:64'],
            /** The percentage as a decimal string from "0" to "100", up to 4 decimal places, such as "7.5". */
            'rate' => ['sometimes', 'string', 'regex:'.StoreTaxRateRequest::RATE_FORMAT, 'numeric', 'max:100'],
        ];
    }

    public function rateName(): ?string
    {
        return $this->has('name') ? trim($this->string('name')->value()) : null;
    }

    public function rate(): ?Decimal
    {
        return $this->has('rate') ? Decimal::of($this->string('rate')->value()) : null;
    }
}
