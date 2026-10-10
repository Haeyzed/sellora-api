<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Data;

/**
 * What changes about how a variant's stock is handled. Anything left null stays as it is.
 */
final readonly class InventorySettingsData
{
    public function __construct(
        public ?bool $tracksStock = null,
        public ?bool $allowsBackorder = null,
        public ?int $lowStockThreshold = null,
        public bool $usesStoreThreshold = false,
    ) {}
}
