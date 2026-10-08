<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's products, newest first, optionally narrowed by status, brand or category, or those in the trash.
 */
final class ListProductsRequest extends PaginatedListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            /** A brand's ID: only its products. */
            'brand' => ['sometimes', 'string', Rule::exists(Brand::class, 'public_id')],
            /** A category's ID: only the products directly in it. */
            'category' => ['sometimes', 'string', Rule::exists(Category::class, 'public_id')],
            /** True for the products in the trash instead of the others. */
            'trashed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Narrows the list to what was asked for.
     *
     * @param  Builder<Product>  $query
     */
    public function applyFilters(Builder $query): void
    {
        if ($this->boolean('trashed')) {
            $query->onlyTrashed();
        }

        $status = $this->validated('status');

        if (is_string($status)) {
            $query->where('status', $status);
        }

        $brand = $this->validated('brand');

        if (is_string($brand)) {
            $query->where('brand_id', Brand::withTrashed()->where('public_id', $brand)->value('id'));
        }

        $category = $this->validated('category');

        if (is_string($category)) {
            $categoryId = Category::withTrashed()->where('public_id', $category)->value('id');
            $query->whereHas('categories', static fn (Builder $categories) => $categories->whereKey($categoryId));
        }
    }
}
