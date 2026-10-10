<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Services;

use App\Shared\Auth\AccountReference;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Enums\StockMovementType;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * The only writer of stock levels: locks them in a fixed order and changes them only together with a stock movement (section 13).
 *
 * Every Action that changes stock runs in one transaction: lock the levels it
 * needs, check its business rule against the locked numbers, then record.
 * Locking always goes by location, then variant, so two transactions that
 * need the same levels never wait on each other in a circle.
 */
final readonly class StockLedger
{
    /**
     * Locks the stock levels of these variants at these locations until the transaction ends, creating any that don't exist yet.
     *
     * @param  list<array{location: int, variant: int}>  $pairs
     * @return array<string, StockItem> Keyed "location:variant".
     *
     * @throws LogicException When called outside a transaction, where the locks would release at once.
     */
    public function lock(array $pairs): array
    {
        if (StockItem::query()->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Stock levels must be locked inside a database transaction.');
        }

        $keys = [];
        foreach ($pairs as $pair) {
            $keys[$pair['location'].':'.$pair['variant']] = $pair;
        }

        $now = now();
        StockItem::query()->insertOrIgnore(array_values(array_map(static fn (array $pair): array => [
            'stock_location_id' => $pair['location'],
            'product_variant_id' => $pair['variant'],
            'on_hand' => 0,
            'reserved' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $keys)));

        return StockItem::query()
            ->where(static function (Builder $query) use ($keys): void {
                foreach ($keys as $pair) {
                    $query->orWhere(static fn (Builder $one) => $one->where('stock_location_id', $pair['location'])->where('product_variant_id', $pair['variant']));
                }
            })
            ->orderBy('stock_location_id')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (StockItem $item): string => $item->stock_location_id.':'.$item->product_variant_id)
            ->all();
    }

    /**
     * Changes a locked stock level and writes the movement that explains it.
     *
     * @throws LogicException When the change would leave a level below zero; callers check their rule first.
     */
    public function record(
        StockItem $item,
        StockMovementType $type,
        int $onHandDelta,
        int $reservedDelta = 0,
        ?string $reason = null,
        ?string $note = null,
        ?AccountReference $causer = null,
        ?StockSource $source = null,
    ): StockMovement {
        $item->on_hand += $onHandDelta;
        $item->reserved += $reservedDelta;

        if ($item->on_hand < 0 || $item->reserved < 0) {
            throw new LogicException('A stock level can never go below zero; the caller must check first.');
        }

        $item->save();

        $movement = new StockMovement;
        $movement->forceFill([
            'stock_item_id' => $item->id,
            'product_variant_id' => $item->product_variant_id,
            'stock_location_id' => $item->stock_location_id,
            'type' => $type,
            'reason' => $reason,
            'note' => $note,
            'on_hand_delta' => $onHandDelta,
            'reserved_delta' => $reservedDelta,
            'on_hand_after' => $item->on_hand,
            'reserved_after' => $item->reserved,
            'causer_type' => $causer?->type,
            'causer_id' => $causer?->publicId,
            'source_type' => $source?->type,
            'source_id' => $source?->id,
        ])->save();

        return $movement;
    }
}
