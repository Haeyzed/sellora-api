<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the store's stock.
 */
enum InventoryPermission: string implements GrantablePermission
{
    /** See stock levels, locations and the history of every change. */
    case InventoryView = 'inventory.view';

    /** Receive stock, correct counts, and change how each variant's stock is handled. */
    case InventoryAdjust = 'inventory.adjust';

    /** See and change what each unit costs the store. Costs are internal and never shown to customers. */
    case InventoryCosts = 'inventory.costs';

    public function group(): string
    {
        return 'inventory';
    }

    public function isSensitive(): bool
    {
        return in_array($this, [self::InventoryCosts], true);
    }
}
