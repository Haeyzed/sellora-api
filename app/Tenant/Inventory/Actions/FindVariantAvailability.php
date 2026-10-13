<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Whether each variant can be bought now, for the storefront: its stock isn't counted, some is available at an active location, or it allows backorders.
 *
 * For other domains (the storefront catalog, Cart, Checkout) to call
 * (section 6). Answers yes or no only; quantities are never shown to
 * customers. One query for any number of variants.
 */
final readonly class FindVariantAvailability
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, bool> By variant ID.
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

        $answers = [];
        foreach ($variantIds as $variantId) {
            $setting = $settings->get($variantId);
            $answers[$variantId] = ($setting !== null && (! $setting->tracks_stock || $setting->allows_backorder)) || $available->has($variantId);
        }

        return $answers;
    }
}
