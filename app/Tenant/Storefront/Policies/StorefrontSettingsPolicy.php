<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Storefront\Enums\StorefrontPermission;

/**
 * Which staff may see and change the storefront settings, and upload the storefront's images. Shoppers read only what is published, through the public storefront endpoints.
 */
final class StorefrontSettingsPolicy
{
    public function view(StaffMember $actor): bool
    {
        return $actor->can(StorefrontPermission::StorefrontView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(StorefrontPermission::StorefrontManage->value);
    }
}
