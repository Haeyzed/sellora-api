<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Orders\Actions\FindBestSellingProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The store's best sellers, for the storefront: the products that sold the most units recently, store-wide or in one category.
 *
 * Orders counts the units (FindBestSellingProducts: paid orders only, never
 * test ones). Only the order of the products' IDs is cached, per store and
 * category, for catalog.best_sellers.cache_minutes; whether each one can be
 * bought, and what it costs, is checked live every time.
 */
final readonly class BestSellers
{
    public function __construct(private FindBestSellingProducts $findBestSellingProducts) {}

    /**
     * More IDs than are shown, best first, so enough remain once the ones shoppers can't buy now are left out.
     *
     * @return list<int>
     */
    public function candidateIds(?Category $category): array
    {
        return Cache::remember(
            'catalog.best-sellers.'.($category === null ? 'all' : $category->id),
            now()->addMinutes(config()->integer('catalog.best_sellers.cache_minutes')),
            fn (): array => $this->findBestSellingProducts->handle(
                config()->integer('catalog.best_sellers.max_shown') * config()->integer('catalog.best_sellers.candidates_per_shown'),
                $category === null ? null : Product::query()->whereHas('categories', static fn (Builder $categories) => $categories->whereKey($category->id)),
            ),
        );
    }
}
