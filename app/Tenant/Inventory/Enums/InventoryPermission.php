<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

/**
 * What staff can be allowed to do with the store's stock.
 */
enum InventoryPermission: string
{
    /** See stock levels, locations and the history of every change. */
    case InventoryView = 'inventory.view';

    /** Receive stock, correct counts, and change how each variant's stock is handled. */
    case InventoryAdjust = 'inventory.adjust';
}
