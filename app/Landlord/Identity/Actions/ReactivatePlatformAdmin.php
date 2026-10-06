<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformTeamRules;

/**
 * Lets a deactivated platform admin sign in again, with the roles they had.
 */
final readonly class ReactivatePlatformAdmin
{
    public function __construct(private PlatformTeamRules $platformTeamRules) {}

    /**
     * @throws CannotManageOwnPlatformAccountException When the actor reactivates themselves.
     */
    public function handle(PlatformAdmin $actor, PlatformAdmin $platformAdmin): PlatformAdmin
    {
        $this->platformTeamRules->ensureNotSelf($actor, $platformAdmin);

        if ($platformAdmin->is_active) {
            return $platformAdmin;
        }

        $platformAdmin->forceFill(['is_active' => true])->save();

        activity('platform_team')
            ->causedBy($actor)
            ->performedOn($platformAdmin)
            ->event('platform_admin_reactivated')
            ->log("Reactivated {$platformAdmin->name}");

        return $platformAdmin;
    }
}
