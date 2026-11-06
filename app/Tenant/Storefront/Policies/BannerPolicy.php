<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Storefront\Enums\StorefrontPermission;

/**
 * Which staff may see the storefront's banners (switched off and scheduled ones included) and manage them. Shoppers see only those shown now, through the public storefront endpoints.
 */
final class BannerPolicy
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
