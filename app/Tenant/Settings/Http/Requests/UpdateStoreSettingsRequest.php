<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Requests;

use App\Shared\Geography\Rules\ContentLocale;
use App\Shared\Geography\Rules\CountryCode;
use App\Shared\Geography\Rules\CurrencyCode;
use App\Shared\Geography\Rules\StateCode;
use App\Shared\Geography\Rules\Timezone;
use App\Shared\Money\TaxMode;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Enums\DimensionUnit;
use App\Tenant\Settings\Enums\WeightUnit;
use App\Tenant\Settings\Models\StoreSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Changing some of the store's settings, which needs the settings.manage permission. Send only what changes.
 */
final class UpdateStoreSettingsRequest extends FormRequest
{
    use ActsAsStaffMember;

    private ?StoreSettings $currentSettings = null;

    public function authorize(): bool
    {
        return $this->actor()->can('update', StoreSettings::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            /** ISO 3166-1 alpha-2, such as "NG". Changing it changes nothing else: currency, timezone and tax mode stay as they are. */
            'country' => ['sometimes', 'string', new CountryCode],
            /** The base currency, ISO 4217, such as "NGN". Can't change once anything in the store is priced. */
            'currency' => ['sometimes', 'string', new CurrencyCode],
            /** IANA, such as "Africa/Lagos". */
            'timezone' => ['sometimes', 'string', new Timezone],
            /** The language content falls back to; must be one of the enabled languages. */
            'default_locale' => ['sometimes', 'string', new ContentLocale],
            /** Every language the store publishes in, from the content languages list, including the default. */
            'enabled_locales' => ['sometimes', 'array', 'min:1', 'max:'.count(config()->array('geography.content_locales'))],
            'enabled_locales.*' => ['required', 'string', 'distinct', new ContentLocale],
            /** Whether prices include tax. Can't change once anything in the store is priced. */
            'tax_mode' => ['sometimes', Rule::enum(TaxMode::class)],
            'weight_unit' => ['sometimes', Rule::enum(WeightUnit::class)],
            'dimension_unit' => ['sometimes', Rule::enum(DimensionUnit::class)],
            /** Shown to customers. */
            'contact_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:254'],
            /** In international format (+234…), or national format for the store's country. Stored and returned in E.164. */
            'contact_phone' => [
                'sometimes', 'nullable', 'string', 'max:40',
                // Built when validating, not here, so the store's country is only looked up for a request that sends a phone.
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$this->storeCountry()])->setData($this->all())->validate($attribute, $value, $fail),
            ],
            /** The store's address, replacing the whole address; null removes it. */
            'address' => ['sometimes', 'nullable', 'array:line1,line2,city,state,postal_code,country'],
            'address.line1' => ['nullable', 'string', 'max:200'],
            'address.line2' => ['nullable', 'string', 'max:200'],
            'address.city' => ['nullable', 'string', 'max:120'],
            /** The state, province or region code within the address country, from that country's states list, such as "LA". */
            'address.state' => ['nullable', 'string', new StateCode($this->addressCountry())],
            /** Free text: postcode formats differ by country. */
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            /** ISO 3166-1 alpha-2. */
            'address.country' => ['required_with:address', 'string', new CountryCode],
        ];
    }

    /**
     * The default language must be one of the enabled ones, counting what isn't being changed.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['default_locale', 'enabled_locales', 'enabled_locales.*'])) {
                    return;
                }

                $defaultLocale = $this->has('default_locale') ? $this->string('default_locale')->value() : $this->currentSettings()->default_locale;
                $enabledLocales = $this->has('enabled_locales') ? $this->array('enabled_locales') : $this->currentSettings()->enabled_locales;

                if (! in_array($defaultLocale, $enabledLocales, true)) {
                    $validator->errors()->add('default_locale', __('validation.custom.default_locale.not_enabled'));
                }
            },
        ];
    }

    /**
     * The validated changes, by settings column.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];
        $fields = [
            'name' => 'name', 'country' => 'country_code', 'currency' => 'currency_code', 'timezone' => 'timezone',
            'default_locale' => 'default_locale', 'tax_mode' => 'tax_mode', 'weight_unit' => 'weight_unit', 'dimension_unit' => 'dimension_unit',
        ];

        foreach ($fields as $field => $column) {
            if ($this->has($field)) {
                $changes[$column] = is_string($this->input($field)) ? trim($this->string($field)->value()) : $this->input($field);
            }
        }

        if ($this->has('enabled_locales')) {
            $changes['enabled_locales'] = array_values($this->array('enabled_locales'));
        }

        if ($this->has('contact_email')) {
            $changes['contact_email'] = $this->filled('contact_email') ? mb_strtolower(trim($this->string('contact_email')->value())) : null;
        }

        if ($this->has('contact_phone')) {
            $changes['contact_phone'] = $this->filled('contact_phone')
                ? (new PhoneNumber($this->string('contact_phone')->value(), $this->storeCountry()))->formatE164()
                : null;
        }

        if ($this->has('address')) {
            $changes += $this->addressChanges();
        }

        return $changes;
    }

    protected function prepareForValidation(): void
    {
        $upper = static fn (mixed $value): mixed => is_string($value) ? mb_strtoupper(trim($value)) : $value;
        $merged = [];

        foreach (['country', 'currency'] as $field) {
            if ($this->has($field)) {
                $merged[$field] = $upper($this->input($field));
            }
        }

        $address = $this->input('address');

        if (is_array($address)) {
            foreach (['state', 'country'] as $field) {
                if (array_key_exists($field, $address)) {
                    $address[$field] = $upper($address[$field]);
                }
            }

            $merged['address'] = $address;
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, string|null>
     */
    private function addressChanges(): array
    {
        $value = fn (string $key): ?string => $this->filled("address.{$key}") ? trim($this->string("address.{$key}")->value()) : null;

        return [
            'address_line1' => $value('line1'),
            'address_line2' => $value('line2'),
            'address_city' => $value('city'),
            'address_state_code' => $value('state'),
            'address_postal_code' => $value('postal_code'),
            'address_country_code' => $value('country'),
        ];
    }

    /**
     * The country a phone number in national format belongs to: the one being set, or the store's.
     */
    private function storeCountry(): string
    {
        $country = $this->input('country');

        return is_string($country) && $country !== '' ? mb_strtoupper($country) : $this->currentSettings()->country_code;
    }

    private function addressCountry(): ?string
    {
        $country = $this->input('address.country');

        return is_string($country) && $country !== '' ? $country : null;
    }

    private function currentSettings(): StoreSettings
    {
        return $this->currentSettings ??= app(FindStoreSettings::class)->handle();
    }
}
