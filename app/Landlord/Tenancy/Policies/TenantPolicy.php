<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Policies;

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Tenancy\Models\Tenant;

/**
 * Who on Sellora's team may see stores, suspend them, and give them features or limits outside their plan.
 */
final class TenantPolicy
{
    public function viewAny(PlatformAdmin $actor): bool
    {
        return $actor->can(PlatformPermission::StoresView->value);
    }

    public function view(PlatformAdmin $actor, Tenant $store): bool
    {
        return $actor->can(PlatformPermission::StoresView->value);
    }

    /**
     * Suspend, reactivate, retry setting up.
     */
    public function manage(PlatformAdmin $actor, Tenant $store): bool
    {
        return $actor->can(PlatformPermission::StoresManage->value);
    }

    /**
     * Grant features and override usage limits.
     */
    public function grant(PlatformAdmin $actor, Tenant $store): bool
    {
        return $actor->can(PlatformPermission::StoresGrant->value);
    }
}
