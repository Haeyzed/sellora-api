<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Tenant\Pricing\Data\CurrencyStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the store's currencies, with its market rate beside the rate prices use.
 *
 * @property CurrencyStatus $resource
 */
final class StoreCurrencyResource extends JsonResource
{
    public function __construct(CurrencyStatus $status)
    {
        parent::__construct($status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->resource;
        $currency = $status->currency;

        return [
            /** ISO 4217. */
            'currency' => $currency->currency_code,
            'is_active' => $currency->is_active,
            /** The margin added to the market rate, as a percentage string with 4 decimal places, such as "2.5000". */
            'margin_percent' => $currency->margin_percent->roundTo(4)->toString(),
            /** The rate the merchant set instead of the market rate, as a decimal string; null to use the market rate. */
            'manual_rate' => $currency->manual_rate?->roundTo(12)->toString(),
            'manual_rate_set_at' => $currency->manual_rate_set_at?->toIso8601String(),
            /**
             * The latest market rate from the base currency, always shown beside the rate in use; null when the platform has none.
             */
            'market_rate' => $status->marketRate === null ? null : ['rate' => $status->marketRate->rate->toString(), 'as_of' => $status->marketRate->asOf->toIso8601String()],
            /**
             * The rate prices are converted at: one unit of the base currency × rate = the amount in this currency. Null when it can't be offered.
             */
            'applied_rate' => $status->appliedRate === null ? null : [
                'rate' => $status->appliedRate->rate->toString(),
                'source' => $status->appliedRate->source->value,
                'as_of' => $status->appliedRate->asOf?->toIso8601String(),
            ],
            /** True when its market rate is too old (48 hours) and no manual rate stands in: customers can't buy in it until rates are fetched again. */
            'is_stale' => $status->isStale,
            /** Whether customers can buy in it now. */
            'is_offered' => $status->isOffered(),
        ];
    }
}
