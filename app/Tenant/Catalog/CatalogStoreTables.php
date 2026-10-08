<?php

declare(strict_types=1);

namespace App\Tenant\Catalog;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the catalog appears in a store export: all of it, including items in the trash. It holds no personal data or secrets.
 */
final class CatalogStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'brands',
            include: ['id', 'public_id', 'name', 'slug', 'description', 'created_at', 'updated_at', 'deleted_at'],
        );

        $registry->table(
            'categories',
            include: ['id', 'public_id', 'parent_id', 'name', 'slug', 'description', 'position', 'created_at', 'updated_at', 'deleted_at'],
        );
    }
}
