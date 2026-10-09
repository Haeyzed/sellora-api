<?php

declare(strict_types=1);

use App\Landlord\Identity\Actions\ChangePlatformAdminPermissions;
use App\Landlord\Identity\Actions\ChangePlatformAdminRoles;
use App\Landlord\Identity\Actions\CreatePlatformRole;
use App\Landlord\Identity\Actions\InvitePlatformAdmin;
use App\Landlord\Identity\Actions\UpdatePlatformRole;
use App\Landlord\Identity\Enums\PlatformPermission;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Exceptions\LastSuperAdminException;
use App\Landlord\Identity\Exceptions\PlatformPermissionsExceedYourOwnException;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Identity\PlatformAdminInvitationNotification;
use App\Landlord\Identity\Services\PlatformRolePermissions;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use Database\Seeders\Landlord\PlatformPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;

/*
 * Section 10: later platform admins join only by a super admin's expiring
 * invitation and choose their own password, then must set up two-factor
 * authentication. Managing the team is for super admins only; nobody changes
 * their own account this way; the platform always keeps an active super
 * admin; a super admin can reset another admin's two-factor authentication.
 */
uses(LazilyRefreshDatabase::class);

const PLATFORM_TEAM_PASSWORD = 'a-long-platform-password';

beforeEach(function (): void {
    Notification::fake();
    $this->seed(PlatformPermissionSeeder::class);

    $this->superAdmin = platformTeamMember('Ada Super', PlatformRole::SuperAdmin->value);
});

function platformTeamMember(string $name, ?string $roleName = null): PlatformAdmin
{
    $platformAdmin = PlatformAdmin::factory()->create(['name' => $name, 'password' => PLATFORM_TEAM_PASSWORD]);
    enableTwoFactor($platformAdmin);

    if ($roleName !== null) {
        $platformAdmin->assignRole(Role::findOrCreate($roleName, PlatformAdmin::GUARD));
    }

    return $platformAdmin;
}

/**
 * @param  array<string, mixed>  $data
 */
function platformTeam(string $method, string $path, array $data = [], ?PlatformAdmin $as = null): TestResponse
{
    forgetSignIns();
    $request = test();

    if ($as !== null) {
        $request = $request->withToken(app(AccessTokenIssuer::class)->issue($as, PlatformAdmin::GUARD, 'test')->plainTextToken);
    }

    $response = $request->json($method, centralUrl('/api/v1/platform/'.$path), $data);
    forgetSignIns();

    return $response;
}

function lastPlatformInvitationToken(string $email): string
{
    $tokens = [];

    Notification::assertSentOnDemand(PlatformAdminInvitationNotification::class, static function (PlatformAdminInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$tokens): bool {
        if ($notifiable->routes['mail'] === $email) {
            parse_str((string) parse_url($notification->acceptUrl(), PHP_URL_QUERY), $query);
            $tokens[] = is_string($query['token'] ?? null) ? $query['token'] : '';
        }

        return true;
    });

    return $tokens === [] ? throw new RuntimeException("No invitation was sent to {$email}.") : end($tokens);
}

it('invites someone who joins with their own password and must set up two-factor authentication first', function (): void {
    $support = Role::findOrCreate('Support', PlatformAdmin::GUARD);

    platformTeam('POST', 'team/invitations', ['email' => 'Bola@Example.com', 'name' => 'Bola', 'roles' => [$support->public_id]], $this->superAdmin)
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.roles.0.name', 'Support');

    expect(PlatformAdmin::query()->where('email', 'bola@example.com')->exists())->toBeFalse();
    $token = lastPlatformInvitationToken('bola@example.com');

    platformTeam('POST', 'auth/invitation-previews', ['token' => $token])->assertOk()->assertJsonPath('data.invited_by', 'Ada Super');

    $accessToken = platformTeam('POST', 'auth/invitation-acceptances', [
        'token' => $token, 'name' => 'Bola Ade', 'password' => PLATFORM_TEAM_PASSWORD, 'password_confirmation' => PLATFORM_TEAM_PASSWORD,
    ])->assertCreated()->json('data.token');

    $this->withToken($accessToken)->getJson(centralUrl('/api/v1/platform/auth/me'))->assertOk()->assertJsonPath('data.roles', ['Support']);
    forgetSignIns();
    $this->withToken($accessToken)->getJson(centralUrl('/api/v1/platform/stores'))->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    forgetSignIns();

    platformTeam('POST', 'auth/invitation-acceptances', [
        'token' => $token, 'name' => 'Someone Else', 'password' => PLATFORM_TEAM_PASSWORD, 'password_confirmation' => PLATFORM_TEAM_PASSWORD,
    ])->assertUnprocessable()->assertJsonPath('code', 'platform_admin_invitation_invalid');

    expect(Activity::query()->pluck('event')->all())->toBe(['platform_admin_invited', 'platform_admin_joined']);
});

it('refuses duplicate invitations, and a resent link replaces the old one', function (): void {
    platformTeam('POST', 'team/invitations', ['email' => $this->superAdmin->email, 'roles' => []], $this->superAdmin)
        ->assertConflict()->assertJsonPath('code', 'platform_admin_already_exists');

    platformTeam('POST', 'team/invitations', ['email' => 'bola@example.com', 'roles' => []], $this->superAdmin)->assertCreated();
    $firstToken = lastPlatformInvitationToken('bola@example.com');
    platformTeam('POST', 'team/invitations', ['email' => 'bola@example.com', 'roles' => []], $this->superAdmin)
        ->assertConflict()->assertJsonPath('code', 'platform_admin_invitation_already_pending');

    $invitation = PlatformAdminInvitation::query()->sole();
    platformTeam('POST', "team/invitations/{$invitation->public_id}/resend", as: $this->superAdmin)->assertOk();

    platformTeam('POST', 'auth/invitation-previews', ['token' => $firstToken])->assertUnprocessable();
    platformTeam('POST', 'auth/invitation-previews', ['token' => lastPlatformInvitationToken('bola@example.com')])->assertOk();

    platformTeam('DELETE', "team/invitations/{$invitation->public_id}", as: $this->superAdmin)->assertNoContent();
    platformTeam('POST', 'auth/invitation-previews', ['token' => lastPlatformInvitationToken('bola@example.com')])->assertUnprocessable();
});

it('lets only super admins manage the team, whatever permissions others have', function (): void {
    $everyPermission = Role::findOrCreate('Everything but team', PlatformAdmin::GUARD);
    $everyPermission->syncPermissions(array_map(static fn (PlatformPermission $permission): string => $permission->value, PlatformPermission::cases()));
    $colleague = platformTeamMember('Chidi', 'Everything but team');

    platformTeam('GET', 'team/admins', as: $colleague)->assertForbidden();
    platformTeam('POST', 'team/invitations', ['email' => 'bola@example.com', 'roles' => []], $colleague)->assertForbidden();
    platformTeam('POST', 'team/roles', ['name' => 'Sneaky', 'permissions' => []], $colleague)->assertForbidden();
    platformTeam('PUT', "team/admins/{$colleague->public_id}/roles", ['roles' => []], $colleague)->assertForbidden();

    platformTeam('GET', 'team/admins', as: $this->superAdmin)->assertOk()->assertJsonCount(2, 'data');
});

it('never lets super admins change their own account, and always keeps an active super admin', function (): void {
    $superAdminRole = Role::findByName(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD);

    platformTeam('PUT', "team/admins/{$this->superAdmin->public_id}/roles", ['roles' => []], $this->superAdmin)
        ->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');
    platformTeam('POST', "team/admins/{$this->superAdmin->public_id}/deactivate", as: $this->superAdmin)
        ->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');

    // Two super admins demoting each other at once: whoever is second finds they'd remove the last one.
    $demotedMeanwhile = platformTeamMember('Bola', PlatformRole::SuperAdmin->value);
    $demotedMeanwhile->syncRoles([]);

    expect(fn () => app(ChangePlatformAdminRoles::class)->handle($demotedMeanwhile, $this->superAdmin, []))->toThrow(LastSuperAdminException::class)
        ->and($this->superAdmin->refresh()->hasRole($superAdminRole))->toBeTrue();
});

it('deactivates an admin, who is signed out at once, and reactivates them', function (): void {
    $colleague = platformTeamMember('Chidi');
    $colleagueToken = app(AccessTokenIssuer::class)->issue($colleague, PlatformAdmin::GUARD, 'test')->plainTextToken;

    platformTeam('POST', "team/admins/{$colleague->public_id}/deactivate", as: $this->superAdmin)->assertOk()->assertJsonPath('data.is_active', false);
    $this->withToken($colleagueToken)->getJson(centralUrl('/api/v1/platform/auth/me'))->assertUnauthorized();
    forgetSignIns();

    platformTeam('POST', "team/admins/{$colleague->public_id}/reactivate", as: $this->superAdmin)->assertOk()->assertJsonPath('data.is_active', true);
});

it('resets another admin\'s two-factor authentication after the super admin confirms their password', function (): void {
    $colleague = platformTeamMember('Chidi');
    $colleagueToken = app(AccessTokenIssuer::class)->issue($colleague, PlatformAdmin::GUARD, 'test')->plainTextToken;

    platformTeam('POST', "team/admins/{$colleague->public_id}/two-factor-reset", ['password' => 'not-the-password'], $this->superAdmin)
        ->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');
    platformTeam('POST', "team/admins/{$this->superAdmin->public_id}/two-factor-reset", ['password' => PLATFORM_TEAM_PASSWORD], $this->superAdmin)
        ->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');

    platformTeam('POST', "team/admins/{$colleague->public_id}/two-factor-reset", ['password' => PLATFORM_TEAM_PASSWORD], $this->superAdmin)
        ->assertOk()->assertJsonPath('data.two_factor_enabled', false);

    $this->withToken($colleagueToken)->getJson(centralUrl('/api/v1/platform/auth/me'))->assertUnauthorized();
    forgetSignIns();
    platformTeam('POST', "team/admins/{$colleague->public_id}/two-factor-reset", ['password' => PLATFORM_TEAM_PASSWORD], $this->superAdmin)
        ->assertConflict()->assertJsonPath('code', 'two_factor_not_enabled');
});

it('manages platform roles from the permission list, audited in the central database', function (): void {
    platformTeam('GET', 'team/permissions', as: $this->superAdmin)->assertOk()->assertJsonCount(count(PlatformPermission::cases()), 'data');

    $roleId = platformTeam('POST', 'team/roles', ['name' => 'Support', 'permissions' => ['stores.view']], $this->superAdmin)
        ->assertCreated()->assertJsonPath('data.permissions', ['stores.view'])->json('data.id');

    platformTeam('POST', 'team/roles', ['name' => 'SUPPORT', 'permissions' => []], $this->superAdmin)
        ->assertUnprocessable()->assertJsonPath('code', 'role_name_taken');
    platformTeam('PUT', "team/roles/{$roleId}", ['name' => 'Support desk', 'permissions' => ['stores.view', 'stores.manage']], $this->superAdmin)
        ->assertOk()->assertJsonPath('data.permissions', ['stores.manage', 'stores.view']);

    $superAdminRole = Role::findByName(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD);
    platformTeam('PUT', "team/roles/{$superAdminRole->public_id}", ['name' => 'Renamed', 'permissions' => []], $this->superAdmin)
        ->assertConflict()->assertJsonPath('code', 'platform_role_protected');

    platformTeamMember('Chidi', 'Support desk');
    platformTeam('DELETE', "team/roles/{$roleId}", as: $this->superAdmin)->assertConflict()->assertJsonPath('code', 'platform_role_in_use');

    $role = Role::query()->where('public_id', $roleId)->sole();
    expect(Audit::query()->where('auditable_type', 'role')->where('auditable_id', (string) $role->id)->where('user_id', $this->superAdmin->id)->count())->toBeGreaterThanOrEqual(2);
});

it('gives a platform admin permissions directly, on top of their roles, audited and logged', function (): void {
    $support = platformTeamMember('Sam Support', 'Support');
    Role::findByName('Support', PlatformAdmin::GUARD)->givePermissionTo(PlatformPermission::StoresView->value);

    platformTeam('PUT', "team/admins/{$support->public_id}/permissions", ['permissions' => [PlatformPermission::StoresExport->value]], $this->superAdmin)
        ->assertOk()
        ->assertJsonPath('data.holds_every_permission', false)
        ->assertJsonPath('data.permissions.*.name', ['stores.export', 'stores.view'])
        ->assertJsonPath('data.permissions.0.sources', [['type' => 'direct', 'role' => null]])
        ->assertJsonPath('data.permissions.1.sources', [['type' => 'role', 'role' => 'Support']]);

    $audit = Audit::query()->where('auditable_type', 'platform_admin')->where('auditable_id', $support->id)->sole();
    $activity = Activity::query()->where('event', 'platform_admin_permissions_changed')->sole();

    expect($support->fresh()?->can(PlatformPermission::StoresExport->value))->toBeTrue()
        ->and($audit->event)->toBe('sync')
        ->and(collect($audit->new_values['permissions'] ?? [])->pluck('name')->all())->toBe(['stores.export'])
        ->and($activity->causer?->is($this->superAdmin))->toBeTrue()
        ->and($activity->properties->all())->toBe(['previous_permissions' => [], 'permissions' => ['stores.export']]);

    platformTeam('GET', "team/admins/{$this->superAdmin->public_id}/permissions", as: $this->superAdmin)
        ->assertOk()
        ->assertJsonPath('data.holds_every_permission', true)
        ->assertJsonCount(count(PlatformPermission::cases()), 'data.permissions')
        ->assertJsonPath('data.permissions.0.sources', [['type' => 'role', 'role' => 'super_admin']]);
});

it('never lets an admin change their own permissions, gives super admins none directly, and is for super admins only', function (): void {
    $otherSuperAdmin = platformTeamMember('Bo Super', PlatformRole::SuperAdmin->value);
    $support = platformTeamMember('Sam Support', 'Support');
    $path = static fn (PlatformAdmin $platformAdmin): string => "team/admins/{$platformAdmin->public_id}/permissions";

    platformTeam('PUT', $path($this->superAdmin), ['permissions' => []], $this->superAdmin)->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');
    platformTeam('PUT', $path($otherSuperAdmin), ['permissions' => ['stores.view']], $this->superAdmin)->assertConflict()->assertJsonPath('code', 'super_admin_protected');
    platformTeam('PUT', $path($support), ['permissions' => ['stores.everything']], $this->superAdmin)->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    platformTeam('PUT', $path($support), ['permissions' => ['stores.view']], $support)->assertForbidden();
    platformTeam('GET', $path($support), as: $support)->assertForbidden();

    expect($support->permissions()->count() + $otherSuperAdmin->permissions()->count())->toBe(0);
});

it('never lets a platform admin grant a permission they don\'t hold, directly, through a role or through an invitation', function (): void {
    // Only super admins reach team management today; the rule is tested directly so it holds if that ever changes.
    Role::findOrCreate('Support', PlatformAdmin::GUARD)->givePermissionTo(PlatformPermission::StoresView->value);
    Role::findOrCreate('Operations', PlatformAdmin::GUARD)->givePermissionTo(PlatformPermission::StoresView->value, PlatformPermission::StoresManage->value);
    $actor = platformTeamMember('Bo Support', 'Support');
    $actor->givePermissionTo(PlatformPermission::StoresExport->value);
    $colleague = platformTeamMember('Cy Colleague');
    $support = Role::findByName('Support', PlatformAdmin::GUARD);
    $operations = Role::findByName('Operations', PlatformAdmin::GUARD);
    $superAdminRole = Role::findByName(PlatformRole::SuperAdmin->value, PlatformAdmin::GUARD);

    // What they hold, through their role or directly, they may pass on.
    app(ChangePlatformAdminPermissions::class)->handle($actor, $colleague, [PlatformPermission::StoresView, PlatformPermission::StoresExport]);
    app(ChangePlatformAdminRoles::class)->handle($actor, $colleague, [$support]);
    expect($colleague->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(['stores.export', 'stores.view']);

    $refusals = [
        'direct permission' => static fn () => app(ChangePlatformAdminPermissions::class)->handle($actor, $colleague, [PlatformPermission::StoresManage]),
        'role with more' => static fn () => app(ChangePlatformAdminRoles::class)->handle($actor, $colleague, [$operations]),
        'super admin role' => static fn () => app(ChangePlatformAdminRoles::class)->handle($actor, $colleague, [$superAdminRole]),
        'new role with more' => static fn () => app(CreatePlatformRole::class)->handle($actor, 'Escalated', [PlatformPermission::StoresManage]),
        'role edited to more' => static fn () => app(UpdatePlatformRole::class)->handle($actor, $support, 'Support', [PlatformPermission::StoresView, PlatformPermission::StoresGrant]),
        'invitation with more' => static fn () => app(InvitePlatformAdmin::class)->handle($actor, 'new.admin@example.com', null, [$operations]),
        'invitation as super admin' => static fn () => app(InvitePlatformAdmin::class)->handle($actor, 'new.super@example.com', null, [$superAdminRole]),
    ];

    foreach ($refusals as $attempt => $grant) {
        expect($grant)->toThrow(PlatformPermissionsExceedYourOwnException::class, null, $attempt);
    }

    expect($colleague->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(['stores.export', 'stores.view'])
        ->and(Role::query()->where('name', 'Escalated')->exists())->toBeFalse()
        ->and(PlatformRolePermissions::namesOf($support->fresh()))->toBe(['stores.view'])
        ->and(PlatformAdminInvitation::query()->count())->toBe(0);
});
