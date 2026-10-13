<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Geography\Rules\CountryCode;
use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Customers\TaxIdFormat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The tax ID a customer enters for their business, such as a VAT number. Its format is checked; the ID itself is checked by staff, never by a registry.
 */
final class SetCustomerTaxIdRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** 4 to 20 letters and digits; spaces, dots, dashes and slashes are ignored. */
            'tax_id' => [
                'required', 'string', 'max:40',
                static fn (string $attribute, mixed $value, Closure $fail) => is_string($value) && TaxIdFormat::isValid($value) ?: $fail(__('validation.regex', ['attribute' => $attribute])),
            ],
            /** ISO 3166-1 alpha-2 of the country that issued it, such as "GB". */
            'country' => ['required', 'string', new CountryCode],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country'))) {
            $this->merge(['country' => mb_strtoupper(trim($this->string('country')->value()))]);
        }
    }
}
