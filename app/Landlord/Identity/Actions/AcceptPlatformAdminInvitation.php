<?php

declare(strict_types=1);

namespace App\Landlord\Identity\Actions;

use App\Landlord\Identity\Exceptions\InvalidPlatformAdminInvitationException;
use App\Landlord\Identity\Exceptions\PlatformAdminAlreadyExistsException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\Services\PlatformAdminInvitationLinks;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\IssuedAccessToken;
use Carbon\CarbonImmutable;

/**
 * Turns an invitation into a platform admin account with the person's own name and password, and signs them in.
 *
 * Until they set up two-factor authentication, the account can only do that,
 * view its profile and sign out. Each link works once: the invitation row is
 * locked while it is used.
 */
final readonly class AcceptPlatformAdminInvitation
{
    public function __construct(private AccessTokenIssuer $accessTokenIssuer) {}

    /**
     * @param  string  $token  The token from the emailed link.
     *
     * @throws InvalidPlatformAdminInvitationException When the link is unknown, expired, cancelled or already used.
     * @throws PlatformAdminAlreadyExistsException When the email became a platform admin's in the meantime.
     */
    public function handle(string $token, string $name, string $password, string $deviceName): IssuedAccessToken
    {
        $platformAdmin = PlatformAdminInvitation::query()->getConnection()->transaction(static function () use ($token, $name, $password): PlatformAdmin {
            $platformAdminInvitation = PlatformAdminInvitation::query()
                ->where('token_hash', PlatformAdminInvitationLinks::hash($token))
                ->lockForUpdate()
                ->first();

            if ($platformAdminInvitation === null || ! $platformAdminInvitation->isPending()) {
                throw new InvalidPlatformAdminInvitationException;
            }

            if (PlatformAdmin::query()->where('email', $platformAdminInvitation->email)->exists()) {
                throw new PlatformAdminAlreadyExistsException;
            }

            $platformAdmin = PlatformAdmin::query()->create([
                'name' => $name,
                'email' => $platformAdminInvitation->email,
                'password' => $password,
                'is_active' => true,
            ]);
            $platformAdmin->syncRoles($platformAdminInvitation->roles);
            $platformAdmin->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();
            $platformAdminInvitation->forceFill(['accepted_at' => CarbonImmutable::now()])->save();

            activity('platform_team')
                ->causedBy($platformAdmin)
                ->performedOn($platformAdmin)
                ->event('platform_admin_joined')
                ->withProperties(['invitation' => $platformAdminInvitation->public_id])
                ->log("{$platformAdmin->name} joined the platform team");

            return $platformAdmin;
        });

        return $this->accessTokenIssuer->issue($platformAdmin, PlatformAdmin::GUARD, $deviceName);
    }
}
