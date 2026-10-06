<?php

declare(strict_types=1);

namespace App\Shared\Money;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * How every amount of money appears in API responses: minor units, currency code and a ready-to-show string.
 *
 * Apps should calculate with "amount" and "currency", and only display
 * "formatted", which follows the request's language.
 *
 * @property Money $resource
 */
final class MoneyResource extends JsonResource
{
    public function __construct(Money $money)
    {
        parent::__construct($money);
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** The amount in the currency's smallest unit, for example 1999 for $19.99, or 1999 for ¥1,999. */
            'amount' => $this->resource->minorAmount(),
            /** The ISO 4217 currency code. */
            'currency' => $this->resource->currency(),
            /** The amount formatted for display in the request's language. Never parse it. */
            'formatted' => $this->resource->format(App::currentLocale()),
        ];
    }
}
