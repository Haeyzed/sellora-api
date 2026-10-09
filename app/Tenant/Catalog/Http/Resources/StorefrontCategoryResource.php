<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Resources;

use App\Shared\Media\Http\Resources\StorefrontImageResource;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as customers see it, in the language they asked for (or the store's default).
 *
 * @property Category $resource
 */
final class StorefrontCategoryResource extends JsonResource
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
        $locale = app()->getLocale();
        $description = $category->getTranslation('description', $locale);
        $image = $category->getFirstMedia(Category::IMAGE);

        return [
            'id' => $category->public_id,
            /** The parent category's ID; null for a top-level category. */
            'parent_id' => $category->parent?->public_id,
            'slug' => $category->slug,
            'name' => $category->getTranslation('name', $locale),
            'description' => $description === '' ? null : $description,
            'image' => $image === null ? null : new StorefrontImageResource($image),
        ];
    }
}
