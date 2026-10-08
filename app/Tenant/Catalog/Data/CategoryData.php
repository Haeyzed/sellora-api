<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Data;

use App\Tenant\Catalog\Models\Category;

/**
 * What a category is created with, or what changes about it. Anything left null stays as it is.
 */
final readonly class CategoryData
{
    /**
     * @param  array<string, string|null>|null  $name  By language; a language set to null loses its translation.
     * @param  array<string, string|null>|null  $description  By language; a language set to null loses its translation.
     * @param  bool  $removesDescription  Removes the description in every language.
     * @param  Category|null  $parent  The category to sit under.
     * @param  bool  $movesToTopLevel  Moves the category to the top level of the tree.
     */
    public function __construct(
        public ?array $name = null,
        public ?array $description = null,
        public bool $removesDescription = false,
        public ?string $slug = null,
        public ?Category $parent = null,
        public bool $movesToTopLevel = false,
    ) {}

    public function changesParent(): bool
    {
        return $this->parent !== null || $this->movesToTopLevel;
    }
}
