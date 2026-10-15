<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Tenant\Inventory\Enums\StockStatus;
use Illuminate\Support\Facades\DB;

/**
 * Whether each variant is in stock, on backorder or out of stock, for shoppers: never how many are left (section 14).
 *
 * For other domains (the storefront catalog, Cart, Checkout) to call
 * (section 6). A variant whose stock isn't counted, or with some available at
 * an active location, is in stock; one with none available that allows
 * backorders is on backorder. Two queries for any number of variants.
 */
final readonly class FindVariantStockStatus
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, StockStatus> By variant ID.
     */
    public function handle(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $available = DB::connection()->table('stock_items as si')
            ->join('stock_locations as l', static fn ($join) => $join->on('l.id', '=', 'si.stock_location_id')->where('l.is_active', '=', true))
            ->whereIn('si.product_variant_id', $variantIds)
            ->groupBy('si.product_variant_id')
            ->havingRaw('sum(si.on_hand - si.reserved) > 0')
            ->pluck('si.product_variant_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        $settings = DB::connection()->table('inventory_items')
            ->whereIn('product_variant_id', $variantIds)
            ->get(['product_variant_id', 'tracks_stock', 'allows_backorder'])
            ->keyBy('product_variant_id');

        $statuses = [];

        foreach ($variantIds as $variantId) {
            $setting = $settings->get($variantId);

            $statuses[$variantId] = match (true) {
                ($setting !== null && ! $setting->tracks_stock) || $available->has($variantId) => StockStatus::InStock,
                $setting !== null && (bool) $setting->allows_backorder => StockStatus::Backorder,
                default => StockStatus::OutOfStock,
            };
        }

        return $statuses;
    }
}
