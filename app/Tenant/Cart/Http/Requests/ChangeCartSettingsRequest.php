<?php

declare(strict_types=1);

namespace App\Tenant\Cart\Http\Requests;

use App\Shared\Geography\Rules\CountryCode;
use App\Tenant\Cart\Data\CartSettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a shopper changes about their cart: its currency, where they are (for the tax and shipping estimate), or the shipping method they chose. Open to guests (with their cart token) and signed-in customers.
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
            /** Where the shopper is, ISO 3166-1 alpha-2, for the tax and shipping estimate only; null for the store's own country. Changing it clears the state unless one is sent too. Left out, it stays as it is. */
            'country_code' => ['sometimes', 'nullable', 'string', new CountryCode],
            /** Their state, from the country's states list (GET geography/countries/{country}/states), such as "LA", in any case; null for none. A code only, never an address. Left out, it stays as it is. */
            'state_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            /** The shipping method's ID, one GET /cart/shipping-options offers; null for none. Left out, it stays as it is. */
            'shipping_method' => ['sometimes', 'nullable', 'string', 'max:26'],
        ];
    }

    public function settings(): CartSettings
    {
        return new CartSettings(
            changesCurrency: $this->has('currency'),
            currency: $this->filled('currency') ? $this->string('currency')->value() : null,
            changesDestination: $this->has('country_code'),
            destinationCountryCode: $this->filled('country_code') ? $this->string('country_code')->upper()->value() : null,
            changesState: $this->has('state_code'),
            destinationStateCode: $this->filled('state_code') ? $this->string('state_code')->value() : null,
            changesShippingMethod: $this->has('shipping_method'),
            shippingMethodId: $this->filled('shipping_method') ? $this->string('shipping_method')->lower()->value() : null,
        );
    }
}
