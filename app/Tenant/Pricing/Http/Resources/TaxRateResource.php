<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Tenant\Pricing\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tax class's rate in a country, or in one of its states.
 *
 * @property TaxRate $resource
 */
final class TaxRateResource extends JsonResource
{
    public function __construct(TaxRate $taxRate)
    {
        parent::__construct($taxRate);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $taxRate = $this->resource;

        return [
            'id' => $taxRate->public_id,
            'tax_class' => new TaxClassResource($taxRate->taxClass),
            'country_code' => $taxRate->country_code,
            /** Null for the country-wide rate. A state's rate replaces the country's; they never add up. */
            'state_code' => $taxRate->state_code,
            'name' => $taxRate->name,
            /** The percentage as a decimal string with 4 decimal places, such as "7.5000". */
            'rate' => $taxRate->rate->roundTo(4)->toString(),
        ];
    }
}
