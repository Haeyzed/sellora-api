<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Enums\ProductLinkType;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Works out which products to suggest beside one, without any learning from shoppers: the merchant's own picks first, then the same category, the same brand, and a similar price.
 *
 * Only the order of the candidates' IDs is cached, for a few minutes per
 * store and product. Whether each one can be bought, and what it costs, is
 * checked live every time, so a cached list never shows a product that has
 * since sold out, gone to the trash or lost its price.
 */
final readonly class SimilarProducts
{
    /**
     * The candidates' IDs in order, never the product itself, each once. More than are shown, so enough remain once the ones shoppers can't buy now are left out.
     *
     * @return list<int>
     */
    public function candidateIdsFor(Product $product): array
    {
        return Cache::remember(
            "catalog.similar-products.{$product->id}",
            now()->addMinutes(config()->integer('catalog.similar_products.cache_minutes')),
            fn (): array => $this->candidates($product),
        );
    }

    /**
     * @return list<int>
     */
    private function candidates(Product $product): array
    {
        $limit = config()->integer('catalog.similar_products.max_shown') * config()->integer('catalog.similar_products.candidates_per_shown');

        // A link never goes to the product itself (a database check), and every other step leaves it out with what was already chosen.
        $chosen = $this->relatedLinks($product);
        $chosen = [...$chosen, ...$this->inSameCategory($product, [$product->id, ...$chosen], $limit)];
        $chosen = [...$chosen, ...$this->ofSameBrand($product, [$product->id, ...$chosen], $limit)];
        $chosen = [...$chosen, ...$this->atSimilarPrice($product, [$product->id, ...$chosen], $limit)];

        return array_slice($chosen, 0, $limit);
    }

    /**
     * The merchant's own related products, in their order.
     *
     * @return list<int>
     */
    private function relatedLinks(Product $product): array
    {
        return array_values(ProductLink::query()->where('product_id', $product->id)->where('type', ProductLinkType::Related)->orderBy('position')->get()->map(static fn (ProductLink $link): int => $link->linked_product_id)->all());
    }

    /**
     * @param  list<int>  $exclude
     * @return list<int>
     */
    private function inSameCategory(Product $product, array $exclude, int $limit): array
    {
        $categoryIds = $product->categories()->pluck('categories.id')->all();

        if ($categoryIds === []) {
            return [];
        }

        return $this->newestBuyable($exclude, $limit, static fn (Builder $query) => $query->whereHas('categories', static fn (Builder $categories) => $categories->whereIn('categories.id', $categoryIds)));
    }

    /**
     * @param  list<int>  $exclude
     * @return list<int>
     */
    private function ofSameBrand(Product $product, array $exclude, int $limit): array
    {
        if ($product->brand_id === null) {
            return [];
        }

        return $this->newestBuyable($exclude, $limit, static fn (Builder $query) => $query->where('brand_id', $product->brand_id));
    }

    /**
     * Products with a variant priced within catalog.similar_products.price_band_percent of this product's lowest price, in the store's own currency.
     *
     * @param  list<int>  $exclude
     * @return list<int>
     */
    private function atSimilarPrice(Product $product, array $exclude, int $limit): array
    {
        $lowest = $product->variants()->whereNotNull('price_amount')->min('price_amount');

        if (! is_numeric($lowest)) {
            return [];
        }

        $band = config()->integer('catalog.similar_products.price_band_percent');
        $from = intdiv((int) $lowest * (100 - $band), 100);
        $to = intdiv((int) $lowest * (100 + $band) + 99, 100);

        return $this->newestBuyable($exclude, $limit, static fn (Builder $query) => $query->whereHas('variants', static fn (Builder $variants) => $variants->whereBetween('price_amount', [$from, $to])));
    }

    /**
     * Products customers can buy that match, newest first.
     *
     * @param  list<int>  $exclude
     * @param  callable(Builder<Product>): mixed  $matching
     * @return list<int>
     */
    private function newestBuyable(array $exclude, int $limit, callable $matching): array
    {
        $query = Product::query()->visibleToCustomers()->whereKeyNot($exclude);
        $matching($query);

        return array_values(array_map(intval(...), $query->orderByDesc('id')->limit($limit)->pluck('id')->all()));
    }
}
