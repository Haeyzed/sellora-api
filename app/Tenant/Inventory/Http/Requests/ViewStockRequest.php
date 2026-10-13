<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Looking at one variant's stock. Needs the inventory.view permission.
 */
final class ViewStockRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StockItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
