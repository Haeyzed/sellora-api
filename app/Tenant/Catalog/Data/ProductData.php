<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Data;

use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;

/**
 * What a product is created with, or what changes about it. Anything left null stays as it is.
 */
final readonly class ProductData
{
    /**
     * @param  array<string, string|null>|null  $name  By language; a language set to null loses its translation.
     * @param  array<string, string|null>|null  $description  By language; a language set to null loses its translation.
     * @param  bool  $removesDescription  Removes the description in every language.
     * @param  bool  $removesBrand  Leaves the product without a brand.
     * @param  list<Category>|null  $categories  Replaces every category it is in; an empty list removes them all.
     * @param  Category|null  $primaryCategory  One of its categories; null picks the first.
     */
    public function __construct(
        public ?array $name = null,
        public ?array $description = null,
        public bool $removesDescription = false,
        public ?string $slug = null,
        public ?Brand $brand = null,
        public bool $removesBrand = false,
        public ?array $categories = null,
        public ?Category $primaryCategory = null,
    ) {}
}
