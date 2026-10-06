<?php

declare(strict_types=1);

namespace App\Tenant\Identity;

use App\Shared\Privacy\Contracts\ClassifiesStoreTables;
use App\Shared\Privacy\StoreExportRegistry;

/**
 * How the team's tables appear in a store export. Passwords, two-factor secrets, recovery codes and link tokens never leave the store.
 */
final class IdentityStoreTables implements ClassifiesStoreTables
{
    public function classify(StoreExportRegistry $registry): void
    {
        $registry->table(
            'staff_members',
            include: ['id', 'public_id', 'name', 'email', 'is_active', 'last_signed_in_at', 'two_factor_confirmed_at', 'created_at', 'updated_at'],
            exclude: [
                'password' => 'Password hash, a secret.',
                'two_factor_secret' => 'Two-factor authentication secret.',
                'two_factor_recovery_codes' => 'Two-factor recovery code hashes, secrets.',
                'two_factor_last_used_timestep' => 'Internal protection against reusing a two-factor code; means nothing outside the store.',
            ],
        );

        $registry->excludeTable('staff_member_password_reset_tokens', 'Password reset tokens are secrets.');

        $registry->table(
            'staff_invitations',
            include: ['id', 'public_id', 'email', 'name', 'invited_by_id', 'expires_at', 'accepted_at', 'revoked_at', 'created_at', 'updated_at'],
            exclude: ['token_hash' => 'Hash of the invitation link token, a secret.'],
        );
        $registry->table('staff_invitation_roles', include: ['staff_invitation_id', 'role_id']);

        $registry->table('ownership_transfers', include: ['id', 'public_id', 'from_staff_member_id', 'to_staff_member_id', 'status', 'expires_at', 'accepted_at', 'cancelled_at', 'created_at', 'updated_at']);
        $registry->table('ownership_transfer_kept_roles', include: ['ownership_transfer_id', 'role_id']);
    }
}
