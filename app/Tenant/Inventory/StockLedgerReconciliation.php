<?php

declare(strict_types=1);

namespace App\Tenant\Inventory;

use App\Shared\Tenancy\Contracts\StoreStockLedger;
use App\Shared\Tenancy\StockLevelMismatch;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates every stock level of the current store from its movements and lists those that don't match.
 *
 * Every change to a level is written with a movement in the same transaction
 * (Services\StockLedger), so a mismatch means something changed a level
 * outside it: a bug, or a hand-edited database.
 */
final readonly class StockLedgerReconciliation implements StoreStockLedger
{
    public function mismatches(): array
    {
        $ledger = DB::connection()->table('stock_movements')
            ->groupBy('stock_item_id')
            ->selectRaw('stock_item_id, sum(on_hand_delta) as on_hand, sum(reserved_delta) as reserved');

        $rows = DB::connection()->table('stock_items as si')
            ->join('product_variants as v', 'v.id', '=', 'si.product_variant_id')
            ->join('stock_locations as l', 'l.id', '=', 'si.stock_location_id')
            ->leftJoinSub($ledger, 'm', 'm.stock_item_id', '=', 'si.id')
            ->whereRaw('(si.on_hand <> coalesce(m.on_hand, 0) or si.reserved <> coalesce(m.reserved, 0))')
            ->orderBy('si.id')
            ->get(['v.public_id as variant_id', 'l.public_id as location_id', 'si.on_hand', 'si.reserved', DB::raw('coalesce(m.on_hand, 0) as ledger_on_hand'), DB::raw('coalesce(m.reserved, 0) as ledger_reserved')]);

        return array_values($rows->map(static fn (object $row): StockLevelMismatch => new StockLevelMismatch(
            variantId: (string) $row->variant_id,
            locationId: (string) $row->location_id,
            onHand: (int) $row->on_hand,
            onHandFromLedger: (int) $row->ledger_on_hand,
            reserved: (int) $row->reserved,
            reservedFromLedger: (int) $row->ledger_reserved,
        ))->all());
    }
}
