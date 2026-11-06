<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Storefront\Enums\StorefrontPermission;

/**
 * Which staff may see the store's pages (drafts included) and manage them. Shoppers see only published pages, through the public storefront endpoints.
 */
final class PagePolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(StorefrontPermission::StorefrontView->value);
    }

    public function view(StaffMember $actor): bool
    {
        return $actor->can(StorefrontPermission::StorefrontView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(StorefrontPermission::StorefrontManage->value);
    }
}
