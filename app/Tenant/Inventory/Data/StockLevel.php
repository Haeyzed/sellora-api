<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

/**
 * A variant's stock at one location, with how it is handled, for lists and screens.
 */
final readonly class StockLevel
{
    /**
     * @param  int  $lowStockThreshold  The variant's own threshold, or the store's.
     */
    public function __construct(
        public string $variantId,
        public string $productId,
        public ?string $sku,
        public string $locationId,
        public int $onHand,
        public int $reserved,
        public bool $tracksStock,
        public bool $allowsBackorder,
        public int $lowStockThreshold,
    ) {}

    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    /** Counted, and none left to sell. */
    public function isOutOfStock(): bool
    {
        return $this->tracksStock && $this->available() <= 0;
    }

    /** Counted, still some left, but at or below the threshold. */
    public function isLow(): bool
    {
        return $this->tracksStock && $this->available() > 0 && $this->available() <= $this->lowStockThreshold;
    }
}
