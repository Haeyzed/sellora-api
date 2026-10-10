<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;

/**
 * Looking at stock: locations, one variant's stock, or a page of its movements. Needs the inventory.view permission.
 */
final class ViewStockRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StockItem::class);
    }
}
