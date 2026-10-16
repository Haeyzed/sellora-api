<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Shared\Geography\AddressFormat;
use App\Shared\Geography\AddressFormats;
use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\PostalCode;
use App\Shared\Geography\Rules\StateCode;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Models\Order;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * An order's corrected shipping address, all of it, checked by its country's rules. Needs the orders.manage permission.
 */
final class CorrectShippingAddressRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $format = $this->addressFormat();

        return [
            'recipient_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'line1' => ['required', 'string', 'max:200'],
            'line2' => ['nullable', 'string', 'max:200'],
            /** Required in countries whose addresses need a city. */
            'city' => [$format?->requiresCity ? 'required' : 'nullable', 'string', 'max:120'],
            /** The state's code from the country's states list, such as "LA". Must be the order's own: a correction never moves an order to another state. */
            'state' => [$format?->requiresState ? 'required' : 'nullable', 'string', StateCode::forAddress($format?->countryCode)],
            /** In the country's postcode format. Required in countries whose addresses need one. */
            'postal_code' => [$format?->requiresPostalCode ? 'required' : 'nullable', 'string', new PostalCode($format?->countryCode)],
            /** ISO 3166-1 alpha-2. Must be the order's own: a correction never moves an order to another country. */
            'country' => ['required', 'string', new CountryCode],
            /** For the courier: international format, or the address country's national format. Stored in E.164. */
            'phone' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$format->countryCode ?? 'ZZ'])->setData($this->all())->validate($attribute, $value, $fail),
            ],
        ];
    }

    public function address(): AddressSnapshot
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->value()) : null;
        $country = $this->string('country')->value();

        return new AddressSnapshot(
            trim($this->string('recipient_name')->value()),
            $text('company'),
            trim($this->string('line1')->value()),
            $text('line2'),
            $text('city'),
            $this->filled('state') ? app(Geography::class)->stateCode($country, $this->string('state')->value()) : null,
            $this->filled('postal_code') ? mb_strtoupper(trim($this->string('postal_code')->value())) : null,
            $country,
            $this->filled('phone') ? (new PhoneNumber($this->string('phone')->value(), $country))->formatE164() : null,
        );
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country'))) {
            $this->merge(['country' => mb_strtoupper(trim($this->string('country')->value()))]);
        }
    }

    private function addressFormat(): ?AddressFormat
    {
        $country = $this->input('country');

        return is_string($country) && $country !== '' ? app(AddressFormats::class)->for($country) : null;
    }
}
