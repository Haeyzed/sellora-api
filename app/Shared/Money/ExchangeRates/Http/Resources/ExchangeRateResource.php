<?php

declare(strict_types=1);

namespace App\Shared\Money\ExchangeRates\Http\Resources;

use App\Shared\Money\ExchangeRates\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One fetched market rate.
 *
 * @property ExchangeRate $resource
 */
final class ExchangeRateResource extends JsonResource
{
    public function __construct(ExchangeRate $exchangeRate)
    {
        parent::__construct($exchangeRate);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** ISO 4217. */
            'currency' => $this->resource->currency,
            /** How many units of the currency one US dollar bought, as a decimal string with 12 decimal places. */
            'units_per_usd' => $this->resource->units_per_usd->roundTo(12)->toString(),
            /** The provider, such as "openexchangerates". */
            'source' => $this->resource->source,
            'fetched_at' => $this->resource->fetched_at->toIso8601String(),
        ];
    }
}
