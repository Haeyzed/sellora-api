<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Inventory\Actions\FindVariantAvailability;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\VariantPricer;

/**
 * Turns products customers can buy into what the storefront shows, priced in the currency asked for, for every storefront list of products alike.
 *
 * Availability and prices are worked out for the whole list at once, in a
 * fixed number of queries, and never cached: a list of product IDs may be
 * cached, but what they cost and whether they can be bought is always read
 * live. Load the products with StorefrontProductResource::relations().
 */
final readonly class StorefrontProductList
{
    public function __construct(
        private FindVariantAvailability $findVariantAvailability,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
    ) {}

    /**
     * @param  iterable<int, Product>  $products
     * @return list<StorefrontProductResource>
     */
    public function resources(iterable $products, ?string $requestedCurrency): array
    {
        $variants = [];
        $variantIds = [];
        $list = [];

        foreach ($products as $product) {
            $list[] = $product;

            foreach ($product->variants as $variant) {
                $variants[] = $variant;
                $variantIds[] = $variant->id;
            }
        }

        $availability = $this->findVariantAvailability->handle($variantIds);
        $prices = $this->variantPricer->priceAt($variants, $this->findExchangeRates->forStorefront($requestedCurrency));

        return array_map(static fn (Product $product): StorefrontProductResource => new StorefrontProductResource($product, $availability, $prices), $list);
    }
}
