<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\CategoryHasSubcategoriesException;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;

/**
 * Moves a category to the trash: customers no longer see it, but it is kept and can be restored.
 *
 * Only a category without subcategories outside the trash can go, so the tree
 * never has a gap in it. Its slug becomes free for another category.
 */
final readonly class TrashCategory
{
    public function __construct(private CategoryTree $categoryTree) {}

    /**
     * @throws CategoryHasSubcategoriesException When it still has subcategories outside the trash.
     */
    public function handle(Category $category): void
    {
        Category::query()->getConnection()->transaction(function () use ($category): void {
            $this->categoryTree->lockForChanges();

            if ($category->children()->exists()) {
                throw new CategoryHasSubcategoriesException;
            }

            $category->delete();
        });
    }
}
