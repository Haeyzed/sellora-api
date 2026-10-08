<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\CategoryData;
use App\Tenant\Catalog\Exceptions\CategoryLoopException;
use App\Tenant\Catalog\Exceptions\CategorySlugTakenException;
use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Changes a category's name, description or slug, or moves it, with its whole branch, under another parent.
 *
 * Languages that aren't sent keep their text. Renaming doesn't change the
 * slug, so links keep working. A moved category goes to the end of its new
 * siblings.
 */
final readonly class UpdateCategory
{
    public function __construct(private CategoryTree $categoryTree) {}

    /**
     * @throws CategoryLoopException When it would move under itself or one of its subcategories.
     * @throws CategoryTooDeepException When its branch would sit deeper than the store allows.
     * @throws CategorySlugTakenException When the new slug is used by another category outside the trash.
     */
    public function handle(Category $category, CategoryData $changes): Category
    {
        return Category::query()->getConnection()->transaction(function () use ($category, $changes): Category {
            if ($changes->changesParent()) {
                $this->move($category, $changes->movesToTopLevel ? null : $changes->parent);
            }

            $this->applyTexts($category, $changes);

            try {
                $category->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new CategorySlugTakenException(previous: $exception);
            }

            return $category;
        });
    }

    /**
     * @throws CategoryLoopException
     * @throws CategoryTooDeepException
     */
    private function move(Category $category, ?Category $newParent): void
    {
        $this->categoryTree->lockForChanges();

        if ($newParent?->id === $category->parent_id) {
            return;
        }

        if ($newParent !== null && $this->categoryTree->isWithinBranchOf($newParent, $category)) {
            throw new CategoryLoopException;
        }

        $this->categoryTree->ensureFits($newParent, $this->categoryTree->heightOf($category));

        $category->parent_id = $newParent?->id;
        $category->position = $this->categoryTree->nextPosition($newParent);
    }

    private function applyTexts(Category $category, CategoryData $changes): void
    {
        if ($changes->name !== null) {
            $category->changeTranslations('name', $changes->name);
        }

        if ($changes->removesDescription) {
            $category->description = null;
        } elseif ($changes->description !== null) {
            $category->changeTranslations('description', $changes->description);
        }

        if ($changes->slug !== null) {
            $category->slug = $changes->slug;
        }
    }
}
