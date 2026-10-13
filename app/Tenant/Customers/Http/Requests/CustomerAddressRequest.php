<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Shared\Geography\AddressFormat;
use App\Shared\Geography\AddressFormats;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\PostalCode;
use App\Shared\Geography\Rules\StateCode;
use App\Tenant\Customers\Concerns\ActsAsCustomer;
use App\Tenant\Customers\Data\AddressDetails;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * A whole address a customer saves, checked by its country's rules (section 3.3): a country requires only the fields it uses.
 *
 * Saving an address replaces all of it, because which fields are required
 * and what a postcode looks like depend on the country.
 */
final class CustomerAddressRequest extends FormRequest
{
    use ActsAsCustomer;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $format = $this->addressFormat();

        return [
            /** A name for the address, such as "Home". */
            'label' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:200'],
            'line2' => ['nullable', 'string', 'max:200'],
            /** Required in countries whose addresses need a city. */
            'city' => [$format?->requiresCity ? 'required' : 'nullable', 'string', 'max:120'],
            /** The state, province or region code within the country, from that country's states list, such as "LA". Required in countries whose addresses need one. */
            'state' => [$format?->requiresState ? 'required' : 'nullable', 'string', new StateCode($format?->countryCode)],
            /** In the country's postcode format. Required in countries whose addresses need one. */
            'postal_code' => [$format?->requiresPostalCode ? 'required' : 'nullable', 'string', new PostalCode($format?->countryCode)],
            /** ISO 3166-1 alpha-2, such as "NG". */
            'country' => ['required', 'string', new CountryCode],
            /** For the courier: international format, or the address country's national format. Stored in E.164. */
            'phone' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$format->countryCode ?? 'ZZ'])->setData($this->all())->validate($attribute, $value, $fail),
            ],
            /** Make this the default delivery address; false stops it being the default. Unchanged when left out. */
            'is_default_shipping' => ['sometimes', 'boolean'],
            /** Make this the default billing address; false stops it being the default. Unchanged when left out. */
            'is_default_billing' => ['sometimes', 'boolean'],
        ];
    }

    public function details(): AddressDetails
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->value()) : null;
        $country = $this->string('country')->value();

        return new AddressDetails(
            fields: [
                'label' => $text('label'),
                'recipient_name' => trim($this->string('recipient_name')->value()),
                'company' => $text('company'),
                'line1' => trim($this->string('line1')->value()),
                'line2' => $text('line2'),
                'city' => $text('city'),
                'state_code' => $text('state'),
                'postal_code' => $this->filled('postal_code') ? mb_strtoupper(trim($this->string('postal_code')->value())) : null,
                'country_code' => $country,
                'phone' => $this->filled('phone') ? (new PhoneNumber($this->string('phone')->value(), $country))->formatE164() : null,
            ],
            defaultShipping: $this->has('is_default_shipping') ? $this->boolean('is_default_shipping') : null,
            defaultBilling: $this->has('is_default_billing') ? $this->boolean('is_default_billing') : null,
        );
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['country', 'state'] as $field) {
            if (is_string($this->input($field))) {
                $merged[$field] = mb_strtoupper(trim($this->string($field)->value()));
            }
        }

        $this->merge($merged);
    }

    /**
     * The format of the address's country, once the country is one we support; null before that, when the country's own rule reports the problem.
     */
    private function addressFormat(): ?AddressFormat
    {
        $country = $this->input('country');

        return is_string($country) && $country !== '' ? app(AddressFormats::class)->for($country) : null;
    }
}
