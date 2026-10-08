<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Brand;

/**
 * Moves a brand to the trash: customers no longer see it, but it is kept and can be restored.
 *
 * Products and old orders that name the brand keep it. Its slug becomes free
 * for another brand.
 */
final readonly class TrashBrand
{
    public function handle(Brand $brand): void
    {
        $brand->delete();
    }
}
