<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\CreatedPlatformAdmin;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

/**
 * Adds a member of Sellora's team who can sign in to the platform admin app, with two-factor authentication already on.
 *
 * Two-factor authentication is mandatory for platform admins, so the account
 * is created with an authenticator secret the person has already proven
 * works, and never exists with a password alone.
 */
final readonly class CreatePlatformAdmin
{
    public function __construct(private TwoFactorAuthenticator $twoFactorAuthenticator) {}

    /**
     * @param  list<PlatformRole>  $roles
     * @param  string  $twoFactorSecret  The authenticator secret the person added to their app.
     * @param  int  $confirmedTimestep  The step of the code they typed to prove it, so that code can't be used again.
     */
    public function handle(string $name, string $email, string $password, array $roles, string $twoFactorSecret, int $confirmedTimestep): CreatedPlatformAdmin
    {
        $recoveryCodes = $this->twoFactorAuthenticator->newRecoveryCodes();

        $platformAdmin = PlatformAdmin::query()->getConnection()->transaction(function () use ($name, $email, $password, $roles, $twoFactorSecret, $confirmedTimestep, $recoveryCodes): PlatformAdmin {
            $platformAdmin = PlatformAdmin::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);

            $platformAdmin->forceFill([
                'two_factor_secret' => $twoFactorSecret,
                'two_factor_confirmed_at' => CarbonImmutable::now(),
                'two_factor_last_used_timestep' => $confirmedTimestep,
                'two_factor_recovery_codes' => array_map($this->twoFactorAuthenticator->hashRecoveryCode(...), $recoveryCodes),
            ])->save();

            foreach ($roles as $role) {
                $platformAdmin->assignRole(Role::findOrCreate($role->value, PlatformAdmin::GUARD));
            }

            return $platformAdmin;
        });

        return new CreatedPlatformAdmin($platformAdmin, $recoveryCodes);
    }
}
