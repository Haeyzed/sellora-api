<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Jobs;

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Services\ProductSearchText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Rebuilds the search text of every product of a renamed brand, on the bulk queue, a few hundred at a time.
 *
 * Runs in the store that dispatched it (tenancy's queue support). Safe to run
 * again: each product's search text is simply rebuilt from what it is now.
 */
final class RefreshBrandProductsSearchText implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $brandId)
    {
        $this->onQueue('bulk');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tenant-bulk-jobs')];
    }

    public function handle(ProductSearchText $productSearchText): void
    {
        Product::withTrashed()->where('brand_id', $this->brandId)->select('id')->chunkById(500, static function ($products) use ($productSearchText): void {
            foreach ($products as $product) {
                $productSearchText->refresh($product->id);
            }
        });
    }
}
