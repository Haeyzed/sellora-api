<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use App\Shared\Geography\Rules\CountryCode;
use App\Tenant\Cart\Data\CartSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a shopper changes about their cart: its currency, or the country tax is estimated to. Open to guests (with their cart token) and signed-in customers.
 */
final class ChangeCartSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** ISO 4217, one the store offers now; null for the store's own. Left out, it stays as it is. */
            'currency' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            /** Where the shopper is, ISO 3166-1 alpha-2, for the tax estimate only; null for the store's own country. Left out, it stays as it is. */
            'country_code' => ['sometimes', 'nullable', 'string', new CountryCode],
        ];
    }

    public function settings(): CartSettings
    {
        return new CartSettings(
            changesCurrency: $this->has('currency'),
            currency: $this->filled('currency') ? $this->string('currency')->value() : null,
            changesDestination: $this->has('country_code'),
            destinationCountryCode: $this->filled('country_code') ? $this->string('country_code')->upper()->value() : null,
        );
    }
}
