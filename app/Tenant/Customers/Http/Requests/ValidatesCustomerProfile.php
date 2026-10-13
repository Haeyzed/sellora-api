<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Http\Requests;

use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Models\StoreSettings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * The profile fields a customer, or staff for them, can change: name, phone and language. Only the fields sent change; null clears an optional one.
 *
 * The store's settings are read only while validating, so reading the rules
 * (the API docs do) never needs a store.
 *
 * @mixin FormRequest
 */
trait ValidatesCustomerProfile
{
    private ?StoreSettings $storeSettings = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            /** In international format (+2348012345678), or in the store country's national format. Stored in E.164. */
            'phone' => [
                'sometimes', 'nullable', 'string', 'max:30',
                fn (string $attribute, mixed $value, Closure $fail) => (new Phone)->international()->country([$this->storeSettings()->country_code])->setData($this->all())->validate($attribute, $value, $fail),
            ],
            /** The language emails to the customer are written in: one of the store's languages, or null for its default. */
            'locale' => [
                'sometimes', 'nullable', 'string',
                fn (string $attribute, mixed $value, Closure $fail) => in_array($value, $this->storeSettings()->enabled_locales, true) ?: $fail(__('validation.in', ['attribute' => $attribute])),
            ],
        ];
    }

    /**
     * The validated changes, with a phone in E.164 format.
     *
     * @return array{name?: string, phone?: string|null, locale?: string|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('name')) {
            $changes['name'] = $this->string('name')->trim()->value();
        }

        if ($this->has('phone')) {
            $changes['phone'] = $this->filled('phone')
                ? (new PhoneNumber($this->string('phone')->value(), $this->storeSettings()->country_code))->formatE164()
                : null;
        }

        if ($this->has('locale')) {
            $changes['locale'] = $this->filled('locale') ? $this->string('locale')->value() : null;
        }

        return $changes;
    }

    private function storeSettings(): StoreSettings
    {
        return $this->storeSettings ??= app(FindStoreSettings::class)->handle();
    }
}
