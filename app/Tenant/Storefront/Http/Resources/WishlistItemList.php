<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Resources;

use App\Tenant\Inventory\Actions\FindVariantAvailability;
use App\Tenant\Pricing\Actions\FindExchangeRates;
use App\Tenant\Pricing\VariantPricer;
use App\Tenant\Storefront\Models\WishlistItem;

/**
 * Turns saved items into what their customer sees, priced in the currency asked for, in a fixed number of queries for the whole page.
 *
 * Load the items with WishlistItemResource::relations().
 */
final readonly class WishlistItemList
{
    public function __construct(
        private FindVariantAvailability $findVariantAvailability,
        private FindExchangeRates $findExchangeRates,
        private VariantPricer $variantPricer,
    ) {}

    /**
     * @param  iterable<int, WishlistItem>  $items
     * @return list<WishlistItemResource>
     */
    public function resources(iterable $items, ?string $requestedCurrency): array
    {
        $list = [];
        $variants = [];

        foreach ($items as $item) {
            $list[] = $item;
            array_push($variants, ...WishlistItemResource::shownVariants($item));
        }

        $availability = $this->findVariantAvailability->handle(array_map(static fn ($variant): int => $variant->id, $variants));
        $prices = $this->variantPricer->priceAt($variants, $this->findExchangeRates->forStorefront($requestedCurrency));

        return array_map(static fn (WishlistItem $item): WishlistItemResource => new WishlistItemResource($item, $availability, $prices), $list);
    }
}
