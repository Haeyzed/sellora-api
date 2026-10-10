<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Enums\InventoryPermission;

/**
 * Who may see and change the store's stock.
 */
final class StockItemPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(InventoryPermission::InventoryView->value);
    }

    public function update(StaffMember $actor): bool
    {
        return $actor->can(InventoryPermission::InventoryAdjust->value);
    }
}
