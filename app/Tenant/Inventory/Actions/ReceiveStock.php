<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Shared\Money\Decimal;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Services\StockLedger;
use LogicException;

/**
 * Adds stock that arrived at a location, such as a delivery from a supplier, recorded as a stock movement.
 *
 * Other domains (Purchasing, later) pass what the stock came from as the source.
 * A unit cost, when given, is recorded on the receipt and becomes the
 * variant's current unit cost.
 */
final readonly class ReceiveStock
{
    public function __construct(
        private StockLedger $stockLedger,
        private ChangeInventorySettings $changeInventorySettings,
    ) {}

    /**
     * @throws LogicException When the quantity isn't a positive whole number, which is a programming mistake.
     */
    public function handle(
        ProductVariant $variant,
        StockLocation $location,
        int $quantity,
        ?string $note = null,
        ?AccountReference $causer = null,
        ?StockSource $source = null,
        ?Decimal $unitCost = null,
    ): StockItem {
        if ($quantity <= 0) {
            throw new LogicException('Received stock is a positive number of units.');
        }

        return StockItem::query()->getConnection()->transaction(function () use ($variant, $location, $quantity, $note, $causer, $source, $unitCost): StockItem {
            $item = $this->stockLedger->lock([['location' => $location->id, 'variant' => $variant->id]])["{$location->id}:{$variant->id}"];
            $this->stockLedger->record($item, StockMovementType::Received, $quantity, note: $note, causer: $causer, source: $source, unitCost: $unitCost);

            if ($unitCost !== null) {
                $this->changeInventorySettings->handle($variant, new InventorySettingsData(unitCost: $unitCost));
            }

            return $item;
        });
    }
}
