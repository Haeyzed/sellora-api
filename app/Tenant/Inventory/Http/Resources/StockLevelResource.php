<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Resources;

use App\Tenant\Inventory\Data\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A variant's stock at one location, in whole units.
 *
 * @property StockLevel $resource
 */
final class StockLevelResource extends JsonResource
{
    public function __construct(StockLevel $level)
    {
        parent::__construct($level);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $level = $this->resource;

        return [
            'variant_id' => $level->variantId,
            'product_id' => $level->productId,
            'sku' => $level->sku,
            'location_id' => $level->locationId,
            /** Physically at the location. */
            'on_hand' => $level->onHand,
            /** Held for checkouts and orders not yet fulfilled. */
            'reserved' => $level->reserved,
            /** On hand minus reserved; below zero when more was sold than is there (backorders, or a recount). */
            'available' => $level->available(),
            /** False when the variant's stock isn't counted: always available. */
            'tracks_stock' => $level->tracksStock,
            'allows_backorder' => $level->allowsBackorder,
            /** The variant's own threshold, or the store's. */
            'low_stock_threshold' => $level->lowStockThreshold,
            'is_low' => $level->isLow(),
            'is_out_of_stock' => $level->isOutOfStock(),
        ];
    }
}
