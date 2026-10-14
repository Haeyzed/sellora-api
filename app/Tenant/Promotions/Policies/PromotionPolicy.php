<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Policies;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Enums\PromotionPermission;

/**
 * Who among the staff may see and manage the store's promotions and their codes.
 */
final class PromotionPolicy
{
    public function viewAny(StaffMember $actor): bool
    {
        return $actor->can(PromotionPermission::PromotionsView->value);
    }

    public function manage(StaffMember $actor): bool
    {
        return $actor->can(PromotionPermission::PromotionsManage->value);
    }
}
