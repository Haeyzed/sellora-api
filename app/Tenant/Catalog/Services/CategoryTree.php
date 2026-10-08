<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Services;

use App\Tenant\Catalog\Exceptions\CategoryTooDeepException;
use App\Tenant\Catalog\Models\Category;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * Measures the category tree (how deep a category sits, how tall its branch is) and serialises changes to its shape.
 *
 * Walking a tree of any depth takes PostgreSQL recursive queries, which the
 * query builder can't express. Only categories outside the trash count: a
 * category can't be trashed while it has subcategories outside the trash,
 * so a trashed one only ever has trashed subcategories.
 */
final readonly class CategoryTree
{
    private const string LOCK_KEY = 'catalog.category_tree';

    public function __construct(private ConfigRepository $config) {}

    /**
     * Holds the store's tree lock until the caller's transaction ends, so two changes can't together make a loop or go too deep.
     *
     * @throws LogicException When called outside a transaction, where the lock would release at once.
     */
    public function lockForChanges(): void
    {
        $connection = $this->connection();

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('The category tree must be locked inside a database transaction.');
        }

        // A PostgreSQL transaction-level advisory lock: released automatically at commit or rollback.
        $connection->select('select pg_advisory_xact_lock(hashtext(?))', [self::LOCK_KEY]);
    }

    /**
     * How many levels down the category sits: 1 for a top-level category.
     */
    public function depthOf(Category $category): int
    {
        $depth = $this->connection()->scalar(
            'with recursive ancestors as (
                select id, parent_id, 1 as depth from categories where id = ?
                union all
                select categories.id, categories.parent_id, ancestors.depth + 1
                from categories join ancestors on categories.id = ancestors.parent_id
            )
            select max(depth) from ancestors',
            [$category->id],
        );

        return (int) $depth;
    }

    /**
     * How many levels the category's branch spans, counting itself: 1 when it has no subcategories outside the trash.
     */
    public function heightOf(Category $category): int
    {
        $height = $this->connection()->scalar(
            'with recursive branch as (
                select id, 1 as level from categories where id = ?
                union all
                select categories.id, branch.level + 1
                from categories join branch on categories.parent_id = branch.id
                where categories.deleted_at is null
            )
            select max(level) from branch',
            [$category->id],
        );

        return (int) $height;
    }

    /**
     * Whether the possible parent is the category itself or sits anywhere below it.
     */
    public function isWithinBranchOf(Category $possibleParent, Category $category): bool
    {
        return (bool) $this->connection()->scalar(
            'with recursive branch as (
                select id from categories where id = ?
                union all
                select categories.id from categories join branch on categories.parent_id = branch.id
            )
            select exists(select 1 from branch where id = ?)',
            [$category->id, $possibleParent->id],
        );
    }

    /**
     * Checks that a branch of the given height fits under the parent without going deeper than the store allows.
     *
     * @param  Category|null  $parent  Null for the top level.
     *
     * @throws CategoryTooDeepException
     */
    public function ensureFits(?Category $parent, int $branchHeight): void
    {
        $maxDepth = $this->config->integer('catalog.category_max_depth');
        $parentDepth = $parent === null ? 0 : $this->depthOf($parent);

        if ($parentDepth + $branchHeight > $maxDepth) {
            throw new CategoryTooDeepException(['max' => $maxDepth]);
        }
    }

    /**
     * The position after the last of the parent's subcategories outside the trash, so a newly placed category goes to the end.
     *
     * @param  Category|null  $parent  Null for the top level.
     */
    public function nextPosition(?Category $parent): int
    {
        $lastPosition = Category::query()->where('parent_id', $parent?->id)->max('position');

        return is_numeric($lastPosition) ? (int) $lastPosition + 1 : 0;
    }

    private function connection(): ConnectionInterface
    {
        return Category::query()->getConnection();
    }
}
