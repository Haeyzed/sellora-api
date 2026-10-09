<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Category;

/**
 * Deletes a category's image, with its file.
 */
final readonly class RemoveCategoryImage
{
    public function handle(Category $category): void
    {
        $category->clearMediaCollection(Category::IMAGE);
    }
}
