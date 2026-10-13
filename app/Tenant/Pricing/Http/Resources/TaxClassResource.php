<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Tenant\Pricing\Models\TaxClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tax class, such as "Standard" or "Exempt".
 *
 * @property TaxClass $resource
 */
final class TaxClassResource extends JsonResource
{
    public function __construct(TaxClass $taxClass)
    {
        parent::__construct($taxClass);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            /** Outside tax: never taxed, and it has no rates. Zero-rated classes are not exempt; their rates are 0%. */
            'is_exempt' => $this->resource->is_exempt,
            /** The class of every variant not given another. Exactly one class is the default. */
            'is_default' => $this->resource->is_default,
            /** Shipping is taxed at this class. When no class is, shipping isn't taxed. */
            'used_for_shipping' => $this->resource->used_for_shipping,
        ];
    }
}
