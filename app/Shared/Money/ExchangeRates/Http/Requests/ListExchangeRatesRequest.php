<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Http\Requests;

use App\Shared\Http\CursorListRequest;

/**
 * A page of fetched market rates, optionally one currency's. For any signed-in platform admin: the route checks who is signed in, and rates are reference data, not store data.
 */
final class ListExchangeRatesRequest extends CursorListRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Only this currency's rates, such as "NGN". */
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    public function currency(): ?string
    {
        return $this->filled('currency') ? $this->string('currency')->value() : null;
    }
}
