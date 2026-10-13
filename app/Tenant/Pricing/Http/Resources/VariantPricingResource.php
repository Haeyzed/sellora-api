<?php

declare(strict_types=1);

namespace App\Tenant\Pricing\Http\Resources;

use App\Shared\Money\MoneyResource;
use App\Tenant\Pricing\Data\VariantPrice;
use App\Tenant\Pricing\Data\VariantPricing;
use App\Tenant\Pricing\Models\FixedPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How one variant is priced beyond its base price: its tax class, fixed prices, and what it costs in each currency.
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
            /**
             * The prices the merchant set per currency, used as they are instead of converting the base price.
             *
             * @var list<array{currency: string, price: array{amount: int, currency: string, formatted: string}, compare_at_price: array{amount: int, currency: string, formatted: string}|null}>
             */
            'fixed_prices' => array_map(static fn (FixedPrice $fixed): array => [
                'currency' => $fixed->currency_code,
                'price' => new MoneyResource($fixed->price),
                'compare_at_price' => $fixed->compare_at_price === null ? null : new MoneyResource($fixed->compare_at_price),
            ], $pricing->fixedPrices),
            /**
             * What one unit costs now in each currency customers can buy in, the base currency first; empty while the variant is unpriced.
             *
             * @var list<array{price: array{amount: int, currency: string, formatted: string}, compare_at_price: array{amount: int, currency: string, formatted: string}|null, is_fixed: bool, includes_tax: bool}>
             */
            'prices' => array_map(static fn (VariantPrice $price): array => [
                'price' => new MoneyResource($price->unitPrice),
                'compare_at_price' => $price->compareAtPrice === null ? null : new MoneyResource($price->compareAtPrice),
                'is_fixed' => $price->isFixed,
                'includes_tax' => $price->includesTax,
            ], $pricing->offeredPrices),
        ];
    }
}
