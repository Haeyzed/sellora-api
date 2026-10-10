<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Tenant\Inventory\Data\StockLevel;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Reads stock levels for lists and screens: every variant outside the trash, whether or not it has ever had stock, with its settings and the threshold that applies.
 */
final readonly class StockLevels
{
    public function __construct(private FindStoreSettings $findStoreSettings) {}

    /**
     * Levels at one location, ordered by variant, optionally only those running low or out of stock.
     *
     * @param  'low'|'out'|null  $status
     */
    public function at(int $locationId, ?string $status = null, ?int $productId = null, ?int $variantId = null): Builder
    {
        $storeThreshold = $this->findStoreSettings->handle()->low_stock_threshold;
        $available = '(coalesce(si.on_hand, 0) - coalesce(si.reserved, 0))';
        $threshold = 'coalesce(ii.low_stock_threshold, ?)';
        $tracks = 'coalesce(ii.tracks_stock, true)';

        return DB::connection()->table('product_variants as v')
            ->join('products as p', static fn ($join) => $join->on('p.id', '=', 'v.product_id')->whereNull('p.deleted_at'))
            ->join('stock_locations as l', static fn ($join) => $join->where('l.id', '=', $locationId))
            ->leftJoin('stock_items as si', static fn ($join) => $join->on('si.product_variant_id', '=', 'v.id')->on('si.stock_location_id', '=', 'l.id'))
            ->leftJoin('inventory_items as ii', 'ii.product_variant_id', '=', 'v.id')
            ->whereNull('v.deleted_at')
            ->when($productId !== null, static fn (Builder $query) => $query->where('v.product_id', $productId))
            ->when($variantId !== null, static fn (Builder $query) => $query->where('v.id', $variantId))
            ->when($status === 'out', static fn (Builder $query) => $query->whereRaw("{$tracks} and {$available} <= 0"))
            ->when($status === 'low', static fn (Builder $query) => $query->whereRaw("{$tracks} and {$available} > 0 and {$available} <= {$threshold}", [$storeThreshold]))
            ->selectRaw(
                "v.id, v.public_id as variant_id, p.public_id as product_id, v.sku, l.public_id as location_id, coalesce(si.on_hand, 0) as on_hand, coalesce(si.reserved, 0) as reserved, {$tracks} as tracks_stock, coalesce(ii.allows_backorder, false) as allows_backorder, {$threshold} as low_stock_threshold",
                [$storeThreshold],
            )
            ->orderBy('v.id');
    }

    public static function fromRow(stdClass $row): StockLevel
    {
        return new StockLevel(
            variantId: (string) $row->variant_id,
            productId: (string) $row->product_id,
            sku: $row->sku === null ? null : (string) $row->sku,
            locationId: (string) $row->location_id,
            onHand: (int) $row->on_hand,
            reserved: (int) $row->reserved,
            tracksStock: (bool) $row->tracks_stock,
            allowsBackorder: (bool) $row->allows_backorder,
            lowStockThreshold: (int) $row->low_stock_threshold,
        );
    }
}
