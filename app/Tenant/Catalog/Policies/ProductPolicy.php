<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Policies;

use App\Tenant\Catalog\Enums\CatalogPermission;
use App\Tenant\Identity\Models\StaffMember;

/**
 * Who may see and change the store's products and their variants.
 */
final class ProductPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogView->value);
    }

    public function view(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogView->value);
    }

    public function create(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogManage->value);
    }

    public function update(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogManage->value);
    }

    public function delete(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogManage->value);
    }

    public function restore(StaffMember $actor): bool
    {
        return $actor->can(CatalogPermission::CatalogManage->value);
    }
}
