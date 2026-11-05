<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Enums;

use App\Shared\Auth\Contracts\GrantablePermission;

/**
 * What staff can be allowed to do with the store's catalog: brands, categories, products and their variants.
 */
enum CatalogPermission: string implements GrantablePermission
{
    /** See the catalog, including drafts, archived items and the trash. */
    case CatalogView = 'catalog.view';

    /** Create, change, move to the trash and restore anything in the catalog. */
    case CatalogManage = 'catalog.manage';

    public function group(): string
    {
        return 'catalog';
    }

    public function isSensitive(): bool
    {
        return false;
    }
}
