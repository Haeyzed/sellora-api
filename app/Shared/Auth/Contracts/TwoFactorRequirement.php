<?php

declare(strict_types=1);

namespace App\Shared\Auth\Contracts;

use App\Shared\Auth\TwoFactor\Contracts\TwoFactorAuthenticatable;

/**
 * Whether an account must use two-factor authentication, for guards where that is a setting rather than a rule.
 *
 * Platform admins must always use it (EnsureTwoFactorIsEnabled). For staff,
 * the store's owner decides (section 10), so Tenant\Settings implements this
 * from the store's settings.
 */
interface TwoFactorRequirement
{
    public function isRequiredFor(TwoFactorAuthenticatable $account): bool;
}
