<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Inventory\Enums\AdjustmentReason;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Exceptions\StockWouldGoNegativeException;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Services\StockLedger;
use LogicException;

/**
 * Corrects how many of a variant are on hand at a location, by an amount ("-2: damaged") or to a counted number ("now 17: recount").
 *
 * Recorded as a stock movement with the reason and who made it. On hand can
 * never go below zero; it may go below what is reserved, because a count
 * records what is really there (the variant is then oversold). A count that
 * matches what is recorded changes nothing.
 */
final readonly class AdjustStock
{
    public function __construct(private StockLedger $stockLedger) {}

    /**
     * @param  int|null  $change  Added (positive) or removed (negative); give this or $count.
     * @param  int|null  $count  The number now on hand; give this or $change.
     *
     * @throws StockWouldGoNegativeException When the change would leave less than nothing on hand.
     */
    public function handle(
        ProductVariant $variant,
        StockLocation $location,
        ?int $change,
        ?int $count,
        AdjustmentReason $reason,
        ?string $note = null,
        ?AccountReference $causer = null,
    ): StockItem {
        if (($change === null) === ($count === null)) {
            throw new LogicException('Adjust stock by a change or to a count, not both or neither.');
        }

        return StockItem::query()->getConnection()->transaction(function () use ($variant, $location, $change, $count, $reason, $note, $causer): StockItem {
            $item = $this->stockLedger->lock([['location' => $location->id, 'variant' => $variant->id]])["{$location->id}:{$variant->id}"];
            $delta = $count !== null ? $count - $item->on_hand : (int) $change;

            if ($item->on_hand + $delta < 0) {
                throw new StockWouldGoNegativeException($count !== null ? 'count' : 'change', $item->on_hand);
            }

            if ($delta !== 0) {
                $this->stockLedger->record($item, StockMovementType::Adjusted, $delta, reason: $reason->value, note: $note, causer: $causer);
            }

            return $item;
        });
    }
}
