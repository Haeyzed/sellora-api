<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as staff see it, with its texts in every language they were written in.
 *
 * @property Category $resource
 */
final class CategoryResource extends JsonResource
{
    public function __construct(Category $category)
    {
        parent::__construct($category);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource;
        $description = $category->getTranslations('description');

        return [
            'id' => $category->public_id,
            /** The ID of the category it sits under; null at the top level. */
            'parent_id' => $this->whenLoaded('parent', static fn (): ?string => $category->parent?->public_id),
            /**
             * By language, such as {"en": "Shirts"}, including languages the store no longer publishes in.
             *
             * @var array<string, string>
             */
            'name' => $category->getTranslations('name'),
            /**
             * By language; null when the category has no description.
             *
             * @var array<string, string>|null
             */
            'description' => $description === [] ? null : $description,
            /** Used in the category's storefront address, such as "mens-shirts". */
            'slug' => $category->slug,
            /** Its place among its siblings, from 0. */
            'position' => $category->position,
            /** How many subcategories it has outside the trash. */
            'subcategories_count' => $this->whenCounted('children'),
            /** When the category was moved to the trash; null when it isn't in the trash. */
            'trashed_at' => $category->deleted_at?->toIso8601String(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
