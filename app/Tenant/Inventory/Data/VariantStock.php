<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

/**
 * Everything about one variant's stock: how it is handled and its level at every active location.
 */
final readonly class VariantStock
{
    /**
     * @param  int|null  $ownLowStockThreshold  Null when it uses the store's threshold.
     * @param  list<StockLevel>  $levels
     */
    public function __construct(
        public string $variantId,
        public bool $tracksStock,
        public bool $allowsBackorder,
        public ?int $ownLowStockThreshold,
        public array $levels,
    ) {}
}
