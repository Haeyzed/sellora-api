<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The currency a storefront request asks prices in.
 *
 * @mixin FormRequest
 */
trait AsksForCurrency
{
    /**
     * @return array<string, list<string>>
     */
    protected function currencyRules(): array
    {
        return [
            /** ISO 4217, such as "USD": prices in that currency while the store offers it (see /catalog/currencies). Otherwise, including for a currency whose exchange rate is out of date, prices come in the store's own currency; every price says which currency it is in. */
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    public function requestedCurrency(): ?string
    {
        $currency = $this->validated('currency');

        return is_string($currency) ? $currency : null;
    }
}
