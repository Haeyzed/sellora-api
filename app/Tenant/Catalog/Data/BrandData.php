<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Data;

/**
 * What a brand is created with, or what changes about it. Anything left null stays as it is.
 */
final readonly class BrandData
{
    /**
     * @param  array<string, string|null>|null  $name  By language; a language set to null loses its translation.
     * @param  array<string, string|null>|null  $description  By language; a language set to null loses its translation.
     * @param  bool  $removesDescription  Removes the description in every language.
     */
    public function __construct(
        public ?array $name = null,
        public ?array $description = null,
        public bool $removesDescription = false,
        public ?string $slug = null,
    ) {}
}
