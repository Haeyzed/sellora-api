<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product as staff see it, with its texts in every language they were written in and its variants.
 *
 * @property Product $resource
 */
final class ProductResource extends JsonResource
{
    /**
     * The relations every response needs, so none is loaded lazily.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['brand:id,public_id', 'primaryCategory:id,public_id', 'categories:id,public_id', 'variants'];

    public function __construct(Product $product)
    {
        parent::__construct($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource;
        $description = $product->getTranslations('description');

        return [
            'id' => $product->public_id,
            'status' => $product->status,
            /**
             * By language, such as {"en": "Linen shirt"}, including languages the store no longer publishes in.
             *
             * @var array<string, string>
             */
            'name' => $product->getTranslations('name'),
            /**
             * By language; null when the product has no description.
             *
             * @var array<string, string>|null
             */
            'description' => $description === [] ? null : $description,
            /** Used in the product's storefront address, such as "linen-shirt". */
            'slug' => $product->slug,
            /** The brand's ID; null without a brand. */
            'brand_id' => $this->whenLoaded('brand', static fn (): ?string => $product->brand?->public_id),
            /**
             * The IDs of every category it is in.
             *
             * @var list<string>
             */
            'category_ids' => $this->whenLoaded('categories', static fn (): array => array_values($product->categories->map(static fn (Category $category): string => $category->public_id)->all())),
            /** The ID of the category it is mainly listed under; null when it is in none. */
            'primary_category_id' => $this->whenLoaded('primaryCategory', static fn (): ?string => $product->primaryCategory?->public_id),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            /** When the product was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $product->deleted_at?->toIso8601String(),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
