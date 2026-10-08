<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Exceptions\CategoryOrderMismatchException;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;

/**
 * Puts a parent's subcategories, or the top-level categories, in a new order.
 *
 * Every subcategory outside the trash must be listed exactly once, so a list
 * made from an outdated screen is refused instead of leaving some out.
 */
final readonly class ReorderCategories
{
    public function __construct(private CategoryTree $categoryTree) {}

    /**
     * @param  Category|null  $parent  Null for the top level.
     * @param  list<string>  $orderedPublicIds  Every subcategory's public ID, in the new order.
     *
     * @throws CategoryOrderMismatchException When the list isn't exactly the parent's subcategories.
     */
    public function handle(?Category $parent, array $orderedPublicIds): void
    {
        Category::query()->getConnection()->transaction(function () use ($parent, $orderedPublicIds): void {
            $this->categoryTree->lockForChanges();

            /** @var array<string, int> $idsByPublicId */
            $idsByPublicId = Category::query()->where('parent_id', $parent?->id)->pluck('id', 'public_id')->all();
            $listed = array_unique($orderedPublicIds);
            $current = array_keys($idsByPublicId);
            sort($listed);
            sort($current);

            if (count($orderedPublicIds) !== count($idsByPublicId) || $listed !== $current) {
                throw new CategoryOrderMismatchException;
            }

            foreach ($orderedPublicIds as $position => $publicId) {
                Category::query()->whereKey($idsByPublicId[$publicId])->update(['position' => $position]);
            }
        });
    }
}
