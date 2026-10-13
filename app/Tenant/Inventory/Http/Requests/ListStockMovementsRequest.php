<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;

/**
 * A page of one variant's stock history. Needs the inventory.view permission.
 */
final class ListStockMovementsRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StockItem::class);
    }
}
