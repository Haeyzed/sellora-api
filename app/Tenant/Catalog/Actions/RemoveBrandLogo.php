<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Actions;

use App\Tenant\Catalog\Models\Brand;

/**
 * Deletes a brand's logo, with its file.
 */
final readonly class RemoveBrandLogo
{
    public function handle(Brand $brand): void
    {
        $brand->clearMediaCollection(Brand::LOGO);
    }
}
