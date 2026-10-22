<?php

declare(strict_types=1);

namespace App\Tenant\Checkout\Http\Requests;

use App\Shared\Geography\AddressFormat;
use App\Shared\Geography\AddressFormats;
use App\Shared\Geography\Geography;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\PostalCode;
use App\Shared\Geography\Rules\StateCode;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\TaxIdFormat;
use App\Tenant\Shipping\Models\ShippingMethod;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * What the shopper tells us at checkout: any of their contact details, addresses (each checked by its country's rules), the way to ship and to pay, their tax ID, their marketing consent, and a note. Only what is sent changes.
 */
final class UpdateCheckoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $signedIn = $this->user() instanceof Customer;

        return [
            /** A guest's email. A signed-in customer always uses their account's, so it can't be sent. */
            'contact.email' => [$signedIn ? 'prohibited' : 'sometimes', 'nullable', 'string', 'email:rfc', 'max:254'],
            'contact.name' => ['sometimes', 'nullable', 'string', 'max:200'],
            /** International format, or the address country's national format. Stored in E.164. */
            'contact.phone' => ['sometimes', 'nullable', 'string', 'max:30', fn (string $attribute, mixed $value, Closure $fail) => $this->phoneRule($attribute, $value, $fail)],
            /** Where it is sent; checked by its country's rules. Not needed for a pickup or when nothing ships. */
            'shipping_address' => ['sometimes', 'nullable', 'array'],
            ...$this->addressRules('shipping_address'),
            /** The billing address, when it isn't the shipping address; null makes it the shipping address again. Needed when nothing is sent to you, for tax. */
            'billing_address' => ['sometimes', 'nullable', 'array'],
            ...$this->addressRules('billing_address'),
            /** The ID of one of the shipping options in the quote; null to choose again. */
            'shipping_method' => ['sometimes', 'nullable', 'string', 'max:26'],
            /** The key of one of the quote's "gateway" or "manual" payment methods, to pay what is left; null to choose again. */
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:96'],
            /** Keys of the quote's "module" payment methods to apply first, in order, replacing the list. */
            'module_payment_methods' => ['sometimes', 'array', 'max:5'],
            'module_payment_methods.*' => ['required', 'string', 'distinct', 'max:96'],
            /** The buyer's tax ID and the country that issued it; null removes it. */
            'buyer_tax_id' => ['sometimes', 'nullable', 'array'],
            /** 4 to 20 letters and digits; spaces, dots, dashes and slashes are ignored. */
            'buyer_tax_id.id' => ['required_with:buyer_tax_id', 'string', 'max:40', static fn (string $attribute, mixed $value, Closure $fail) => is_string($value) && TaxIdFormat::isValid($value) ?: $fail(__('validation.regex', ['attribute' => $attribute]))],
            'buyer_tax_id.country' => ['required_with:buyer_tax_id', 'string', new CountryCode],
            /** Whether you agree to marketing by email and by SMS, replacing both: only true is a yes; anything missing is a no. */
            'marketing_consents' => ['sometimes', 'array'],
            'marketing_consents.email' => ['sometimes', 'boolean'],
            'marketing_consents.sms' => ['sometimes', 'boolean'],
            'customer_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The validated changes, by checkout column.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->value()) : null;

        if ($this->has('contact.email')) {
            $changes['contact_email'] = $this->filled('contact.email') ? mb_strtolower(trim($this->string('contact.email')->value())) : null;
        }

        if ($this->has('contact.name')) {
            $changes['contact_name'] = $text('contact.name');
        }

        if ($this->has('contact.phone')) {
            $changes['contact_phone'] = $this->filled('contact.phone') ? (new PhoneNumber($this->string('contact.phone')->value(), $this->phoneCountry()))->formatE164() : null;
        }

        foreach (['shipping_address', 'billing_address'] as $kind) {
            if ($this->has($kind)) {
                $changes[$kind] = $this->filled($kind) ? $this->address($kind)->toArray() : null;
            }
        }

        if ($this->has('shipping_method')) {
            $changes['shipping_method_id'] = $this->filled('shipping_method') ? ShippingMethod::query()->where('public_id', $this->string('shipping_method')->value())->value('id') : null;
        }

        if ($this->has('payment_method')) {
            $changes['payment_method'] = $text('payment_method');
        }

        if ($this->has('module_payment_methods')) {
            $changes['module_payment_methods'] = array_values(array_map(strval(...), $this->array('module_payment_methods')));
        }

        if ($this->has('buyer_tax_id')) {
            $changes['buyer_tax_id'] = $this->filled('buyer_tax_id') ? TaxIdFormat::normalise($this->string('buyer_tax_id.id')->value()) : null;
            $changes['buyer_tax_id_country'] = $this->filled('buyer_tax_id') ? $this->string('buyer_tax_id.country')->upper()->value() : null;
        }

        if ($this->has('marketing_consents')) {
            // Only an explicit yes is consent.
            $changes['marketing_email_consent'] = $this->input('marketing_consents.email') === true;
            $changes['marketing_sms_consent'] = $this->input('marketing_consents.sms') === true;
        }

        if ($this->has('customer_note')) {
            $changes['customer_note'] = $text('customer_note');
        }

        return $changes;
    }

    protected function prepareForValidation(): void
    {
        foreach (['shipping_address', 'billing_address'] as $kind) {
            $country = $this->input("{$kind}.country");

            if (is_string($country)) {
                $this->merge([$kind => [...$this->array($kind), 'country' => mb_strtoupper(trim($country))]]);
            }
        }

        $taxIdCountry = $this->input('buyer_tax_id.country');

        if (is_string($taxIdCountry)) {
            $this->merge(['buyer_tax_id' => [...$this->array('buyer_tax_id'), 'country' => mb_strtoupper(trim($taxIdCountry))]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function addressRules(string $kind): array
    {
        $format = $this->addressFormat($kind);
        $required = "required_with:{$kind}";

        return [
            "{$kind}.recipient_name" => [$required, 'string', 'max:120'],
            "{$kind}.company" => ['nullable', 'string', 'max:120'],
            "{$kind}.line1" => [$required, 'string', 'max:200'],
            "{$kind}.line2" => ['nullable', 'string', 'max:200'],
            "{$kind}.city" => [$format?->requiresCity ? $required : 'nullable', 'string', 'max:120'],
            "{$kind}.state" => [$format?->requiresState ? $required : 'nullable', 'string', StateCode::forAddress($format?->countryCode)],
            "{$kind}.postal_code" => [$format?->requiresPostalCode ? $required : 'nullable', 'string', new PostalCode($format?->countryCode)],
            "{$kind}.country" => [$required, 'string', new CountryCode],
            "{$kind}.phone" => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$format->countryCode ?? 'ZZ'])->setData($this->all())->validate($attribute, $value, $fail),
            ],
        ];
    }

    private function address(string $kind): AddressSnapshot
    {
        $text = fn (string $key): ?string => $this->filled("{$kind}.{$key}") ? trim($this->string("{$kind}.{$key}")->value()) : null;
        $country = $this->string("{$kind}.country")->value();

        return new AddressSnapshot(
            (string) $text('recipient_name'),
            $text('company'),
            (string) $text('line1'),
            $text('line2'),
            $text('city'),
            $this->filled("{$kind}.state") ? app(Geography::class)->stateCode($country, $this->string("{$kind}.state")->value()) : null,
            $this->filled("{$kind}.postal_code") ? mb_strtoupper((string) $text('postal_code')) : null,
            $country,
            $this->filled("{$kind}.phone") ? (new PhoneNumber($this->string("{$kind}.phone")->value(), $country))->formatE164() : null,
        );
    }

    private function addressFormat(string $kind): ?AddressFormat
    {
        $country = $this->input("{$kind}.country");

        return is_string($country) && $country !== '' ? app(AddressFormats::class)->for($country) : null;
    }

    /**
     * The country a national phone number is read in: the billing address's, else the shipping address's.
     */
    private function phoneCountry(): string
    {
        foreach (['billing_address.country', 'shipping_address.country'] as $key) {
            $country = $this->input($key);

            if (is_string($country) && $country !== '') {
                return $country;
            }
        }

        return 'ZZ';
    }

    private function phoneRule(string $attribute, mixed $value, Closure $fail): void
    {
        (new Phone)->international()->country([$this->phoneCountry()])->setData($this->all())->validate($attribute, $value, $fail);
    }
}
