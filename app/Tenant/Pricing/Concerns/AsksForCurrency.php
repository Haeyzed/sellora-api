<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The currency a storefront request asks prices in, for any domain that shows shoppers prices (section 6: a public concern).
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
