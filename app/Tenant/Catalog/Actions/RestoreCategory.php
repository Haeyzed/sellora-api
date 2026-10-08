<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\CategoryParentInTrashException;
use App\Tenant\Catalog\Exceptions\CategoryRestoreConflictException;
use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Brings a category back from the trash, as the last of its siblings. Restoring a category that isn't in the trash changes nothing.
 *
 * Its parent may have moved deeper while it was in the trash, so the depth
 * limit is checked again.
 */
final readonly class RestoreCategory
{
    public function __construct(private CategoryTree $categoryTree) {}

    /**
     * @throws CategoryParentInTrashException When its parent is in the trash.
     * @throws CategoryTooDeepException When its parent now sits too deep for it.
     * @throws CategoryRestoreConflictException When another category now uses its slug.
     */
    public function handle(Category $category): Category
    {
        if (! $category->trashed()) {
            return $category;
        }

        return Category::query()->getConnection()->transaction(function () use ($category): Category {
            $this->categoryTree->lockForChanges();
            $parent = $category->parent;

            if ($parent?->trashed() === true) {
                throw new CategoryParentInTrashException;
            }

            $this->categoryTree->ensureFits($parent, branchHeight: 1);
            $category->position = $this->categoryTree->nextPosition($parent);

            try {
                $category->restore();
            } catch (UniqueConstraintViolationException $exception) {
                throw new CategoryRestoreConflictException(previous: $exception);
            }

            return $category;
        });
    }
}
