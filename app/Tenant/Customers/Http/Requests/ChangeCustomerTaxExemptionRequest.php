<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff making a customer tax-exempt, or ending it. Needs customers.tax_exemption.
 */
final class ChangeCustomerTaxExemptionRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manageTaxExemption', Customer::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** True once you have checked the customer's tax ID; false ends the exemption. */
            'is_tax_exempt' => ['required', 'boolean'],
        ];
    }
}
