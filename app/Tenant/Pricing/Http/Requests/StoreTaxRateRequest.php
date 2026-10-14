<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Requests;

use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Money\Decimal;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a tax class's rate for a country or one of its states. Needs the pricing.manage permission.
 */
final class StoreTaxRateRequest extends FormRequest
{
    use ActsAsStaffMember;

    /** A percentage from 0 to 100 with up to 4 decimal places, as a string. */
    public const string RATE_FORMAT = '/^\d{1,3}(\.\d{1,4})?$/';

    public function authorize(): bool
    {
        return $this->actor()->can('manage', TaxClass::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryCode = $this->input('country_code');

        return [
            /** The tax class's ID. An exempt class has no rates. */
            'tax_class' => ['required', 'string', Rule::exists(TaxClass::class, 'public_id')->where('is_exempt', false)],
            /** ISO 3166-1 alpha-2, such as "NG". */
            'country_code' => ['required', 'string', new CountryCode],
            /** The state's code from the country's states list (GET geography/countries/{country}/states), such as "LA", in any case; leave out or null for the country-wide rate. A state's rate replaces the country's. Refused for a country that has no states list, where only country-wide rates apply. */
            'state_code' => ['sometimes', 'nullable', 'string', StateCode::forTaxRate(is_string($countryCode) ? $countryCode : null)],
            /** What the tax is called on receipts, such as "VAT" or "Sales tax". */
            'name' => ['required', 'string', 'max:64'],
            /** The percentage as a decimal string from "0" to "100", up to 4 decimal places, such as "7.5". "0" makes the class zero-rated there. */
            'rate' => ['required', 'string', 'regex:'.self::RATE_FORMAT, 'numeric', 'max:100'],
        ];
    }

    public function taxClass(): TaxClass
    {
        return TaxClass::query()->where('public_id', $this->string('tax_class')->value())->firstOrFail();
    }

    public function countryCode(): string
    {
        return $this->string('country_code')->value();
    }

    public function stateCode(): ?string
    {
        return $this->filled('state_code') ? app(Geography::class)->stateCode($this->countryCode(), $this->string('state_code')->value()) : null;
    }

    public function rateName(): string
    {
        return trim($this->string('name')->value());
    }

    public function rate(): Decimal
    {
        return Decimal::of($this->string('rate')->value());
    }
}
