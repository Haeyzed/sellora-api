<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Requests;

use App\Landlord\Tenancy\Data\StoreRegistrationData;
use App\Landlord\Tenancy\HostingRegions;
use App\Landlord\Tenancy\Services\StoreSubdomains;
use App\Shared\Geography\Geography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * What a merchant enters to register a store.
 */
final class StartStoreRegistrationRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule|Password|In|Closure>>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'min:2', 'max:120'],
            /** The store's address on the platform domain: lower-case letters, numbers and single hyphens, 3 to 63 characters. */
            'subdomain' => [
                'required', 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(StoreSubdomains::class)->isReserved($value)) {
                        $fail(__('validation.custom.subdomain.reserved'));
                    }
                },
            ],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            /** ISO 3166-1 alpha-2 code, such as "NG", from the countries list. Sets the store's currency, timezone, content language and tax mode, which the merchant can change later. */
            'country_code' => [
                'required', 'string', 'size:2',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || app(Geography::class)->defaultsFor($value) === null) {
                        $fail(__('validation.custom.country_code.unknown'));
                    }
                },
            ],
            /** Required when the country has several timezones, such as the United States; otherwise the country's only one is used. */
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            /** One of the regions from the hosting regions list. */
            'hosting_region' => ['required', 'string', Rule::in(app(HostingRegions::class)->codes())],
            /** The IDs of every legal document version in force, from the legal documents list. */
            'accepted_legal_documents' => ['required', 'array', 'min:1', 'max:20'],
            'accepted_legal_documents.*' => ['required', 'string', 'ulid'],
        ];
    }

    /**
     * The timezone must be one of the country's.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('country_code') || $validator->errors()->has('timezone')) {
                    return;
                }

                $timezones = app(Geography::class)->defaultsFor($this->countryCode())->timezones ?? [];
                $chosen = $this->filled('timezone') ? $this->string('timezone')->value() : null;

                if ($chosen === null && count($timezones) !== 1) {
                    $validator->errors()->add('timezone', __('validation.custom.timezone.required_for_country'));
                } elseif ($chosen !== null && ! in_array($chosen, $timezones, true)) {
                    $validator->errors()->add('timezone', __('validation.custom.timezone.not_in_country'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => is_string($this->input('subdomain')) ? mb_strtolower(trim($this->input('subdomain'))) : $this->input('subdomain'),
            'country_code' => is_string($this->input('country_code')) ? mb_strtoupper(trim($this->input('country_code'))) : $this->input('country_code'),
        ]);
    }

    public function registrationData(): StoreRegistrationData
    {
        /** @var list<string> $acceptedLegalDocumentIds */
        $acceptedLegalDocumentIds = array_values($this->array('accepted_legal_documents'));

        return new StoreRegistrationData(
            storeName: trim($this->string('store_name')->value()),
            subdomain: $this->string('subdomain')->value(),
            ownerName: trim($this->string('owner_name')->value()),
            email: mb_strtolower(trim($this->string('email')->value())),
            password: $this->string('password')->value(),
            countryCode: $this->countryCode(),
            timezone: $this->filled('timezone')
                ? $this->string('timezone')->value()
                : (app(Geography::class)->defaultsFor($this->countryCode())->timezones ?? [])[0],
            hostingRegion: $this->string('hosting_region')->value(),
            acceptedLegalDocumentIds: $acceptedLegalDocumentIds,
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }

    private function countryCode(): string
    {
        return $this->string('country_code')->value();
    }
}
