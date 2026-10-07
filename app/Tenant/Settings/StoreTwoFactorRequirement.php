<?php

declare(strict_types=1);

namespace App\Tenant\Settings;

use App\Shared\Auth\Contracts\TwoFactorRequirement;
use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;

/**
 * Staff must use two-factor authentication when the store's owner requires it. Customers and drivers never have to.
 */
final readonly class StoreTwoFactorRequirement implements TwoFactorRequirement
{
    public function isRequiredFor(TwoFactorAuthenticatable $account): bool
    {
        return $account instanceof StaffMember
            && StoreSettings::query()->whereKey(1)->value('require_staff_two_factor') === true;
    }
}
