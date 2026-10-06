<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\CannotManageOwnPlatformAccountException;
use App\Landlord\Identity\Exceptions\TwoFactorNotSetUpException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Services\PlatformTeamRules;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\CurrentPasswordCheck;
use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;

/**
 * Turns off two-factor authentication for a platform admin who lost both their phone and their recovery codes, so they can set it up again.
 *
 * Only another super admin can do it, after confirming their own password.
 * The admin is signed out everywhere; when they next sign in with their
 * password they can only set up two-factor authentication again.
 */
final readonly class ResetPlatformAdminTwoFactor
{
    public function __construct(
        private PlatformTeamRules $platformTeamRules,
        private CurrentPasswordCheck $currentPasswordCheck,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $actorPassword  The super admin's own current password.
     *
     * @throws CannotManageOwnPlatformAccountException When the actor resets their own.
     * @throws TooManyIncorrectAttemptsException After too many wrong passwords.
     * @throws IncorrectCurrentPasswordException When the actor's password is wrong.
     * @throws TwoFactorNotSetUpException When the admin hasn't set two-factor authentication up.
     */
    public function handle(PlatformAdmin $actor, PlatformAdmin $platformAdmin, string $actorPassword): PlatformAdmin
    {
        $this->platformTeamRules->ensureNotSelf($actor, $platformAdmin);
        $this->currentPasswordCheck->ensureCorrect($actor, $actorPassword);

        if (! $platformAdmin->hasTwoFactorEnabled()) {
            throw new TwoFactorNotSetUpException;
        }

        return PlatformAdmin::query()->getConnection()->transaction(function () use ($actor, $platformAdmin): PlatformAdmin {
            $platformAdmin->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ])->save();
            $this->accessTokenIssuer->revokeAll($platformAdmin);

            activity('platform_team')
                ->causedBy($actor)
                ->performedOn($platformAdmin)
                ->event('platform_admin_two_factor_reset')
                ->log("Reset two-factor authentication for {$platformAdmin->name}");

            return $platformAdmin;
        });
    }
}
