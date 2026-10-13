<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Shared\Http\PageListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;

/**
 * A page of the store's stock locations. Needs the inventory.view permission.
 */
final class ListStockLocationsRequest extends PageListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StockItem::class);
    }
}
