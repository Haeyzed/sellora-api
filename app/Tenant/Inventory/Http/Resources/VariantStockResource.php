<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Resources;

use App\Tenant\Inventory\Data\VariantStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One variant's stock: how it is handled and its level at every active location.
 *
 * @property VariantStock $resource
 */
final class VariantStockResource extends JsonResource
{
    public function __construct(VariantStock $stock)
    {
        parent::__construct($stock);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = $this->resource;

        return [
            'variant_id' => $stock->variantId,
            /** False when its stock isn't counted: always available. */
            'tracks_stock' => $stock->tracksStock,
            /** Whether it may still be ordered when none is available. */
            'allows_backorder' => $stock->allowsBackorder,
            /** Its own threshold; null when it uses the store's. */
            'low_stock_threshold' => $stock->ownLowStockThreshold,
            'levels' => StockLevelResource::collection($stock->levels),
        ];
    }
}
