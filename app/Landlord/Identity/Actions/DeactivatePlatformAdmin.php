<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformTeamRules;
use App\Shared\Auth\AccessTokenIssuer;

/**
 * Removes someone from Sellora's team: they are signed out everywhere at once and can't sign in again.
 *
 * The account is kept, so the activity log still shows who did what, and it
 * can be reactivated.
 */
final readonly class DeactivatePlatformAdmin
{
    public function __construct(
        private PlatformTeamRules $platformTeamRules,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @throws CannotManageOwnPlatformAccountException When the actor deactivates themselves.
     * @throws LastSuperAdminException When they are the last active super admin.
     */
    public function handle(PlatformAdmin $actor, PlatformAdmin $platformAdmin): PlatformAdmin
    {
        $this->platformTeamRules->ensureNotSelf($actor, $platformAdmin);

        if (! $platformAdmin->is_active) {
            return $platformAdmin;
        }

        return PlatformAdmin::query()->getConnection()->transaction(function () use ($actor, $platformAdmin): PlatformAdmin {
            $this->platformTeamRules->ensureAnotherSuperAdminRemains($platformAdmin);

            $platformAdmin->forceFill(['is_active' => false])->save();
            $this->accessTokenIssuer->revokeAll($platformAdmin);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($platformAdmin)
                ->event('platform_admin_deactivated')
                ->log("Deactivated {$platformAdmin->name}");

            return $platformAdmin;
        });
    }
}
