<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Tenant\Pricing\Data\VariantPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How one variant is priced beyond its base price.
 *
 * @property VariantPricing $resource
 */
final class VariantPricingResource extends JsonResource
{
    public function __construct(VariantPricing $pricing)
    {
        parent::__construct($pricing);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = $this->resource;

        return [
            'variant_id' => $pricing->variantId,
            /**
             * The tax class it is taxed at.
             *
             * @var array{id: string, name: string, is_exempt: bool}
             */
            'tax_class' => ['id' => $pricing->taxClass->id, 'name' => $pricing->taxClass->name, 'is_exempt' => $pricing->taxClass->isExempt],
            /** True when it has no class of its own and follows the store's default. */
            'uses_default_tax_class' => $pricing->usesDefaultTaxClass,
        ];
    }
}
