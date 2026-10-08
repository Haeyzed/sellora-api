<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Data\CategoryData;
use App\Tenant\Catalog\Exceptions\CategorySlugTakenException;
use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adds a category to the store's tree, at the top level or under another category, as the last of its siblings.
 *
 * Without a chosen slug, one is made from the name in the default language.
 */
final readonly class CreateCategory
{
    public function __construct(private CategoryTree $categoryTree) {}

    /**
     * @throws CategoryTooDeepException When it would sit deeper than the store allows.
     * @throws CategorySlugTakenException When the chosen slug is used by another category outside the trash.
     */
    public function handle(CategoryData $categoryData): Category
    {
        return Category::query()->getConnection()->transaction(function () use ($categoryData): Category {
            $this->categoryTree->lockForChanges();
            $this->categoryTree->ensureFits($categoryData->parent, branchHeight: 1);

            $category = new Category;
            $category->changeTranslations('name', $categoryData->name ?? []);

            if ($categoryData->description !== null) {
                $category->changeTranslations('description', $categoryData->description);
            }

            if ($categoryData->slug !== null) {
                $category->slug = $categoryData->slug;
            }

            $category->parent_id = $categoryData->parent?->id;
            $category->position = $this->categoryTree->nextPosition($categoryData->parent);

            try {
                $category->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw new CategorySlugTakenException(previous: $exception);
            }

            return $category;
        });
    }
}
