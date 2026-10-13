<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Tenant\Pricing\Data\AppliedExchangeRate;
use App\Tenant\Pricing\Enums\ExchangeRateSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A currency customers can buy in. Rates and margins are the merchant's business and never shown.
 *
 * @property AppliedExchangeRate $resource
 */
final class StorefrontCurrencyResource extends JsonResource
{
    public function __construct(AppliedExchangeRate $rate)
    {
        parent::__construct($rate);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** ISO 4217; ask for prices in it with ?currency= on the catalog. */
            'currency' => $this->resource->currency,
            /** The store's own currency, which prices are set in. */
            'is_base' => $this->resource->source === ExchangeRateSource::Base,
        ];
    }
}
