<?php

declare(strict_types=1);

namespace App\Tenant\Inventory\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Inventory\Models\StockItem;
use Illuminate\Validation\Rule;

/**
 * A page of stock levels at one location, optionally only those running low or out of stock, or one product's.
 */
final class ListStockLevelsRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;
    use ChoosesStockLocation;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', StockItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** The location's ID; the store's default location when left out. */
            'location' => $this->locationRules(),
            /** "low" for counted variants at or below their low-stock threshold with some left, "out" for counted variants with none left to sell. */
            'status' => ['sometimes', Rule::in(['low', 'out'])],
            /** A product's ID: only its variants. */
            'product' => ['sometimes', 'string', Rule::exists(Product::class, 'public_id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return 'low'|'out'|null
     */
    public function status(): ?string
    {
        $status = $this->validated('status');

        return $status === 'low' || $status === 'out' ? $status : null;
    }

    public function productId(): ?int
    {
        $product = $this->validated('product');

        return is_string($product) ? (int) Product::query()->where('public_id', $product)->value('id') : null;
    }
}
