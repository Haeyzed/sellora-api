<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Requests;

use App\Shared\Http\PaginatedListRequest;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the products customers can buy, optionally searched or narrowed to a category or brand. Anyone may ask.
 */
final class ListStorefrontProductsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Words to find in the product's name (in any of the store's languages), SKUs, barcodes or brand. */
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            /** A category's ID: only products in it. */
            'category' => ['sometimes', 'string', Rule::exists(Category::class, 'public_id')->whereNull('deleted_at')],
            /** A brand's ID: only its products. */
            'brand' => ['sometimes', 'string', Rule::exists(Brand::class, 'public_id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $search = $this->validated('search');
        $category = $this->validated('category');
        $brand = $this->validated('brand');

        if (is_string($search)) {
            $query->matching($search);
        }

        if (is_string($category)) {
            $query->whereHas('categories', static fn (Builder $categories) => $categories->where('categories.public_id', $category));
        }

        if (is_string($brand)) {
            $query->whereHas('brand', static fn (Builder $brands) => $brands->where('public_id', $brand));
        }
    }
}
