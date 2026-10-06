<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Actions;

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\IssuedAccessToken;
use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Tenant\Identity\Exceptions\InvalidStaffInvitationException;
use App\Tenant\Identity\Exceptions\StaffMemberAlreadyExistsException;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\Services\StaffAccountLimit;
use App\Tenant\Identity\Services\StaffInvitationLinks;
use Carbon\CarbonImmutable;

/**
 * Turns an invitation into a staff member account with the person's own name and password, and signs them in.
 *
 * Each link works once: the invitation row is locked while it is used, so two
 * requests with the same link can't both create an account.
 */
final readonly class AcceptStaffInvitation
{
    public function __construct(
        private StaffAccountLimit $staffAccountLimit,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {}

    /**
     * @param  string  $token  The token from the emailed link.
     *
     * @throws InvalidStaffInvitationException When the link is unknown, expired, cancelled or already used.
     * @throws StaffMemberAlreadyExistsException When the email became a staff member's in the meantime.
     * @throws UsageLimitReachedException When the plan has no room left, for example after a downgrade.
     */
    public function handle(string $token, string $name, string $password, string $deviceName): IssuedAccessToken
    {
        $staffMember = StaffInvitation::query()->getConnection()->transaction(function () use ($token, $name, $password): StaffMember {
            $staffInvitation = StaffInvitation::query()
                ->where('token_hash', StaffInvitationLinks::hash($token))
                ->lockForUpdate()
                ->first();

            if ($staffInvitation === null || ! $staffInvitation->isPending()) {
                throw new InvalidStaffInvitationException;
            }

            if (StaffMember::query()->where('email', $staffInvitation->email)->exists()) {
                throw new StaffMemberAlreadyExistsException;
            }

            $this->staffAccountLimit->ensureRoomForOneMore(replacing: $staffInvitation);

            $staffMember = StaffMember::query()->create([
                'name' => $name,
                'email' => $staffInvitation->email,
                'password' => $password,
                'is_active' => true,
            ]);
            $staffMember->syncRoles($staffInvitation->roles);
            $staffMember->forceFill(['last_signed_in_at' => CarbonImmutable::now()])->save();
            $staffInvitation->forceFill(['accepted_at' => CarbonImmutable::now()])->save();

            activity('team')
                ->causedBy($staffMember)
                ->performedOn($staffMember)
                ->event('staff_joined')
                ->withProperties(['invitation' => $staffInvitation->public_id])
                ->log("{$staffMember->name} joined the team");

            return $staffMember;
        });

        return $this->accessTokenIssuer->issue($staffMember, StaffMember::GUARD, $deviceName);
    }
}
