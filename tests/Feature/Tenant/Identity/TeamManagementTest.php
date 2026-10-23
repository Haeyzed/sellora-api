<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffInvitationNotification;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 10 and 14: staff join only by invitation and choose their own
 * password; nobody can grant permissions they don't hold, manage someone more
 * powerful, or change their own roles; the owner is untouchable here; the
 * plan's staff limit counts active staff and pending invitations; and nothing
 * (links, role IDs) crosses from one store to another.
 */
uses(TruncatesCentralDatabase::class);

const TEAM_PASSWORD = 'a-long-staff-password';

beforeEach(function (): void {
    Notification::fake();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);

    $this->store = createStore('first-store');
    allowStaffAccounts($this->store->getTenantKey(), 10);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['name' => 'Olu Owner', 'email' => 'owner@example.com', 'password' => TEAM_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('invites by email without creating an account, and the person joins with their own password and roles', function (): void {
    $cashier = roleWith('Cashier', []);

    $invitation = teamRequest('POST', 'team/invitations', ['email' => ' Sam@Example.com', 'name' => 'Sam', 'roles' => [$cashier->public_id]], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.email', 'sam@example.com')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.roles.0.name', 'Cashier')
        ->assertJsonPath('data.invited_by.name', 'Olu Owner')
        ->assertJsonMissingPath('data.token_hash')
        ->json('data');
    expect($this->store->run(static fn (): bool => StaffMember::query()->where('email', 'sam@example.com')->exists()))->toBeFalse();

    $token = '';
    Notification::assertSentOnDemand(StaffInvitationNotification::class, static function (StaffInvitationNotification $notification) use (&$token): bool {
        $token = emailedLinkFragment($notification->acceptUrl())['token'] ?? '';

        return str_starts_with($notification->acceptUrl(), 'https://first-store.'.config('platform.domain').'/admin/accept-invitation#token=');
    });
    expect($this->store->run(static fn (): string => StaffInvitation::query()->sole()->token_hash))->toBe(hash('sha256', $token));

    teamRequest('POST', 'auth/invitation-previews', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.email', 'sam@example.com')
        ->assertJsonPath('data.roles', ['Cashier']);

    $accessToken = teamRequest('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Sam Smith', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
        ->assertCreated()
        ->json('data.token');

    $this->withToken($accessToken)->getJson(storeUrl('first-store', '/api/v1/staff/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'sam@example.com')
        ->assertJsonPath('data.name', 'Sam Smith')
        ->assertJsonPath('data.roles', ['Cashier']);
    tenancy()->end();

    teamRequest('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Someone Else', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff_invitation_invalid');
    expect($invitation['id'])->toBeString();
});

it('refuses expired, cancelled and unknown links with the same error', function (): void {
    $expiredToken = inviteAndCaptureToken('late@example.com');
    $this->travel(8)->days();
    $cancelledToken = inviteAndCaptureToken('cancelled@example.com');
    $cancelled = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->where('email', 'cancelled@example.com')->sole());
    teamRequest('DELETE', 'team/invitations/'.$cancelled->public_id, as: $this->owner)->assertNoContent();

    foreach ([$expiredToken, $cancelledToken, str_repeat('x', 64)] as $token) {
        teamRequest('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Late', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'staff_invitation_invalid');
    }

    teamRequest('DELETE', 'team/invitations/'.$cancelled->public_id, as: $this->owner)->assertConflict()->assertJsonPath('code', 'staff_invitation_not_pending');
});

it('refuses to invite a current staff member or someone already invited, and resending replaces the link', function (): void {
    teamRequest('POST', 'team/invitations', ['email' => 'owner@example.com', 'roles' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'staff_member_already_exists');

    $firstToken = inviteAndCaptureToken('sam@example.com');
    teamRequest('POST', 'team/invitations', ['email' => 'SAM@example.com', 'roles' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'staff_invitation_already_pending');

    $invitation = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->sole());
    Notification::fake();
    teamRequest('POST', 'team/invitations/'.$invitation->public_id.'/resend', as: $this->owner)->assertOk();
    Notification::assertSentOnDemandTimes(StaffInvitationNotification::class, 1);

    teamRequest('POST', 'auth/invitation-previews', ['token' => $firstToken])->assertUnprocessable()->assertJsonPath('code', 'staff_invitation_invalid');
});

it('counts active staff and pending invitations towards the plan\'s staff limit, but not the owner or deactivated staff', function (): void {
    // One place beyond the owner, who never counts.
    allowStaffAccounts($this->store->getTenantKey(), 1);

    teamRequest('POST', 'team/invitations', ['email' => 'first@example.com', 'roles' => []], $this->owner)->assertCreated();
    teamRequest('POST', 'team/invitations', ['email' => 'second@example.com', 'roles' => []], $this->owner)
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');

    $first = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->sole());
    teamRequest('DELETE', 'team/invitations/'.$first->public_id, as: $this->owner)->assertNoContent();
    teamRequest('POST', 'team/invitations', ['email' => 'second@example.com', 'roles' => []], $this->owner)->assertCreated();

    $deactivated = $this->store->run(static fn (): StaffMember => StaffMember::factory()->deactivated()->create());
    teamRequest('POST', 'team/members/'.$deactivated->public_id.'/reactivate', as: $this->owner)->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');
});

it('never lets staff grant permissions they don\'t hold, through a role or an invitation', function (): void {
    $roleEditor = colleagueWith('editor@example.com', roleWith('Role editor', ['roles.view', 'roles.manage', 'staff.invite']));
    $powerful = roleWith('Powerful', ['staff.manage']);

    teamRequest('POST', 'team/roles', ['name' => 'Sneaky', 'permissions' => ['roles.manage', 'staff.manage']], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');
    teamRequest('PUT', 'team/roles/'.$powerful->public_id, ['name' => 'Powerful', 'permissions' => []], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');
    teamRequest('POST', 'team/invitations', ['email' => 'friend@example.com', 'roles' => [$powerful->public_id]], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');

    teamRequest('POST', 'team/roles', ['name' => 'Helper', 'permissions' => ['roles.view']], $roleEditor)->assertCreated();
});

it('never gives the Owner role by invitation or role change', function (): void {
    $ownerRole = $this->store->run(static fn (): Role => Role::findByName(StaffRole::Owner->value, StaffMember::GUARD));
    $colleague = colleagueWith('sam@example.com');

    teamRequest('POST', 'team/invitations', ['email' => 'friend@example.com', 'roles' => [$ownerRole->public_id]], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
    teamRequest('PUT', 'team/members/'.$colleague->public_id.'/roles', ['roles' => [$ownerRole->public_id]], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
});

it('never lets staff manage themselves, the owner, or someone more powerful', function (): void {
    $manager = colleagueWith('manager@example.com', roleWith('Manager', ['staff.view', 'staff.manage']));
    $supervisor = colleagueWith('supervisor@example.com', roleWith('Supervisor', ['staff.view', 'staff.manage', 'roles.manage']));

    teamRequest('PUT', 'team/members/'.$manager->public_id.'/roles', ['roles' => []], $manager)->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');
    teamRequest('POST', 'team/members/'.$this->owner->public_id.'/deactivate', as: $manager)->assertForbidden()->assertJsonPath('code', 'store_owner_protected');
    teamRequest('POST', 'team/members/'.$supervisor->public_id.'/deactivate', as: $manager)->assertForbidden()->assertJsonPath('code', 'permissions_exceed_your_own');
});

it('deactivates a colleague, signing them out at once, and reactivates them with their roles', function (): void {
    $cashierRole = roleWith('Cashier', []);
    $colleague = colleagueWith('sam@example.com', $cashierRole);
    $colleagueToken = staffTokenFor($colleague, 'first-store');

    teamRequest('POST', 'team/members/'.$colleague->public_id.'/deactivate', as: $this->owner)->assertOk()->assertJsonPath('data.is_active', false);
    $this->withToken($colleagueToken)->getJson(storeUrl('first-store', '/api/v1/staff/auth/me'))->assertUnauthorized();
    tenancy()->end();

    teamRequest('POST', 'team/members/'.$colleague->public_id.'/reactivate', as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.roles.0.name', 'Cashier');

    expect($this->store->run(static fn (): array => Activity::query()->orderBy('id')->pluck('event')->all()))->toBe(['staff_deactivated', 'staff_reactivated']);
});

it('changes a colleague\'s roles, which applies to their very next request', function (): void {
    $colleague = colleagueWith('sam@example.com');
    $viewer = roleWith('Team viewer', ['staff.view']);
    $colleagueToken = staffTokenFor($colleague, 'first-store');

    $this->withToken($colleagueToken)->getJson(storeUrl('first-store', '/api/v1/staff/team/members'))->assertForbidden();
    tenancy()->end();
    forgetSignIns();

    teamRequest('PUT', 'team/members/'.$colleague->public_id.'/roles', ['roles' => [$viewer->public_id]], $this->owner)->assertOk()->assertJsonPath('data.roles.0.name', 'Team viewer');

    $this->withToken($colleagueToken)->getJson(storeUrl('first-store', '/api/v1/staff/team/members'))->assertOk()->assertJsonCount(2, 'data');
});

it('refuses every team endpoint to staff without the matching permission', function (string $method, string $path): void {
    $nobody = colleagueWith('nobody@example.com');
    $colleague = colleagueWith('sam@example.com');
    $role = roleWith('Cashier', []);
    $path = strtr($path, ['{member}' => $colleague->public_id, '{role}' => $role->public_id]);

    teamRequest($method, $path, ['email' => 'x@example.com', 'name' => 'Some role', 'roles' => [], 'permissions' => []], $nobody)->assertForbidden();
})->with([
    'list members' => ['GET', 'team/members'],
    'change roles' => ['PUT', 'team/members/{member}/roles'],
    'deactivate' => ['POST', 'team/members/{member}/deactivate'],
    'list invitations' => ['GET', 'team/invitations'],
    'invite' => ['POST', 'team/invitations'],
    'list roles' => ['GET', 'team/roles'],
    'create role' => ['POST', 'team/roles'],
    'update role' => ['PUT', 'team/roles/{role}'],
    'delete role' => ['DELETE', 'team/roles/{role}'],
    'list permissions' => ['GET', 'team/permissions'],
]);

it('manages roles: unique names ignoring case, a protected Owner role, and no deleting roles in use', function (): void {
    $ownerRole = $this->store->run(static fn (): Role => Role::findByName(StaffRole::Owner->value, StaffMember::GUARD));

    $role = teamRequest('POST', 'team/roles', ['name' => 'Cashier', 'permissions' => ['staff.view']], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.permissions', ['staff.view'])
        ->json('data');

    teamRequest('POST', 'team/roles', ['name' => 'cashier', 'permissions' => []], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('code', 'role_name_taken');
    teamRequest('POST', 'team/roles', ['name' => 'OWNER', 'permissions' => []], $this->owner)->assertJsonPath('code', 'role_name_taken');
    teamRequest('POST', 'team/roles', ['name' => 'Typo', 'permissions' => ['staff.everything']], $this->owner)->assertJsonValidationErrors('permissions.0');
    teamRequest('PUT', 'team/roles/'.$ownerRole->public_id, ['name' => 'Boss', 'permissions' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'role_protected');
    teamRequest('DELETE', 'team/roles/'.$ownerRole->public_id, as: $this->owner)->assertConflict()->assertJsonPath('code', 'role_protected');

    teamRequest('PUT', 'team/roles/'.$role['id'], ['name' => 'Senior cashier', 'permissions' => ['staff.view', 'roles.view']], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.name', 'Senior cashier')
        ->assertJsonPath('data.permissions', ['roles.view', 'staff.view']);

    $audits = $this->store->run(static fn (): array => Audit::query()->orderBy('id')->get(['event', 'old_values', 'new_values'])->toArray());
    expect(array_column($audits, 'event'))->toContain('updated', 'sync')
        ->and(json_encode($audits))->toContain('roles.view');

    colleagueWith('sam@example.com', $this->store->run(static fn (): Role => Role::findByName('Senior cashier', StaffMember::GUARD)));
    teamRequest('DELETE', 'team/roles/'.$role['id'], as: $this->owner)->assertConflict()->assertJsonPath('code', 'role_in_use');

    teamRequest('GET', 'team/roles', as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.0.name', 'owner')
        ->assertJsonPath('data.0.grants_everything', true)
        ->assertJsonPath('data.1.staff_count', 1);
});

it('holds case-insensitive role names in the store database, and audits changes made outside a web request', function (): void {
    $this->store->run(static function (): void {
        $role = Role::query()->create(['name' => 'Packer', 'guard_name' => StaffMember::GUARD]);

        expect(static fn () => Role::query()->create(['name' => 'PACKER', 'guard_name' => StaffMember::GUARD]))
            ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);

        $role->update(['name' => 'Senior packer']);

        expect(Audit::query()->where('auditable_id', $role->id)->where('event', 'updated')->sole()->new_values)
            ->toBe(['name' => 'Senior packer']);
    });
});

it('lists the permissions roles can grant, with descriptions', function (): void {
    teamRequest('GET', 'team/permissions', as: $this->owner)
        ->assertOk()
        ->assertJsonFragment(['name' => 'staff.invite', 'description' => __('permissions.staff.invite')]);
});

it('describes every permission a store\'s or the platform\'s roles can grant', function (): void {
    $permissionNames = [...app(StaffPermissionCatalogue::class)->all(), ...array_column(PlatformPermission::cases(), 'value')];

    expect(array_values(array_filter($permissionNames, static fn (string $name): bool => ! Lang::has("permissions.{$name}", 'en', false))))->toBe([]);
});

it('keeps invitations and roles inside their own store', function (): void {
    $secondStore = createStore('second-store');
    allowStaffAccounts($secondStore->getTenantKey(), 10);
    $secondOwner = $secondStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create(['email' => 'owner@second.example.com']);
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    $secondStoreRole = $secondStore->run(static fn (): Role => Role::findOrCreate('Second store role', StaffMember::GUARD));

    $token = inviteAndCaptureToken('sam@example.com');

    teamRequest('POST', 'auth/invitation-previews', ['token' => $token], subdomain: 'second-store')->assertUnprocessable()->assertJsonPath('code', 'staff_invitation_invalid');
    teamRequest('POST', 'team/invitations', ['email' => 'x@example.com', 'roles' => [$secondStoreRole->public_id]], $this->owner)->assertJsonValidationErrors('roles.0');
    teamRequest('GET', 'team/members', as: $secondOwner, subdomain: 'second-store')->assertOk()->assertJsonCount(1, 'data');
});

it('deletes invitations from the store once their link has been expired for the retention period', function (): void {
    config(['retention.periods.staff_invitations' => 30]);
    inviteAndCaptureToken('old@example.com');
    $this->travel(40)->days();
    inviteAndCaptureToken('new@example.com');

    $this->store->run(static function (): void {
        PurgeExpiredRecords::dispatchSync();

        expect(StaffInvitation::query()->pluck('email')->all())->toBe(['new@example.com']);
    });
});

it('gives a team member permissions directly, on top of their roles, audited and logged', function (): void {
    $manager = colleagueWith('manager@example.com', roleWith('Manager', ['staff.view', 'staff.manage', 'catalog.view', 'settings.view']));
    $cashier = colleagueWith('cashier@example.com', roleWith('Cashier', ['settings.view']));

    teamRequest('GET', 'catalog/brands', as: $cashier)->assertForbidden();

    teamRequest('PUT', "team/members/{$cashier->public_id}/permissions", ['permissions' => ['catalog.view']], $manager)
        ->assertOk()
        ->assertJsonPath('data.holds_every_permission', false)
        ->assertJsonPath('data.permissions', [
            ['name' => 'catalog.view', 'description' => __('permissions.catalog.view'), 'sources' => [['type' => 'direct', 'role' => null]]],
            ['name' => 'settings.view', 'description' => __('permissions.settings.view'), 'sources' => [['type' => 'role', 'role' => 'Cashier']]],
        ]);

    teamRequest('GET', 'catalog/brands', as: $cashier)->assertOk();

    $this->store->run(function () use ($cashier, $manager): void {
        $audit = Audit::query()->where('auditable_type', 'staff_member')->where('auditable_id', $cashier->id)->sole();
        $activity = Activity::query()->where('event', 'staff_permissions_changed')->sole();

        expect($audit->event)->toBe('sync')
            ->and(collect($audit->new_values['permissions'] ?? [])->pluck('name')->all())->toBe(['catalog.view'])
            ->and($activity->causer?->is($manager))->toBeTrue()
            ->and($activity->properties->all())->toBe(['previous_permissions' => [], 'permissions' => ['catalog.view']]);
    });

    teamRequest('PUT', "team/members/{$cashier->public_id}/permissions", ['permissions' => []], $manager)->assertOk()->assertJsonCount(1, 'data.permissions');
    teamRequest('GET', 'catalog/brands', as: $cashier)->assertForbidden();
});

it('shows where each permission comes from: every role that gives it, and direct', function (): void {
    $viewer = colleagueWith('viewer@example.com', roleWith('Viewer', ['staff.view']));
    $both = colleagueWith('both@example.com', roleWith('Packer', ['catalog.view']), roleWith('Picker', ['catalog.view']));
    $this->store->run(static fn () => $both->givePermissionTo('catalog.view'));

    teamRequest('GET', "team/members/{$both->public_id}/permissions", as: $viewer)
        ->assertOk()
        ->assertJsonPath('data.permissions.0.name', 'catalog.view')
        ->assertJsonPath('data.permissions.0.sources', [['type' => 'role', 'role' => 'Packer'], ['type' => 'role', 'role' => 'Picker'], ['type' => 'direct', 'role' => null]]);

    $owner = teamRequest('GET', "team/members/{$this->owner->public_id}/permissions", as: $viewer)->assertOk()->assertJsonPath('data.holds_every_permission', true);
    expect($owner->json('data.permissions.*.name'))->toBe(app(StaffPermissionCatalogue::class)->all())
        ->and(collect($owner->json('data.permissions.*.sources'))->unique()->values()->all())->toBe([[['type' => 'role', 'role' => 'owner']]]);
});

it('gives direct permissions under the same rules as roles', function (): void {
    $manager = colleagueWith('manager@example.com', roleWith('Manager', ['staff.view', 'staff.manage']));
    $supervisor = colleagueWith('supervisor@example.com', roleWith('Supervisor', ['staff.view', 'staff.manage', 'roles.manage']));
    $cashier = colleagueWith('cashier@example.com');
    $viewer = colleagueWith('viewer@example.com', roleWith('Viewer', ['staff.view']));
    $permissions = static fn (StaffMember $staffMember): string => "team/members/{$staffMember->public_id}/permissions";

    teamRequest('PUT', $permissions($cashier), ['permissions' => ['catalog.manage']], $manager)->assertForbidden()->assertJsonPath('code', 'permissions_exceed_your_own');
    teamRequest('PUT', $permissions($manager), ['permissions' => ['staff.view']], $manager)->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');
    teamRequest('PUT', $permissions($this->owner), ['permissions' => []], $manager)->assertForbidden()->assertJsonPath('code', 'store_owner_protected');
    teamRequest('PUT', $permissions($supervisor), ['permissions' => []], $manager)->assertForbidden()->assertJsonPath('code', 'permissions_exceed_your_own');
    teamRequest('PUT', $permissions($cashier), ['permissions' => ['staff.everything']], $manager)->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    teamRequest('PUT', $permissions($cashier), ['permissions' => ['staff.view']], $viewer)->assertForbidden();
    teamRequest('GET', $permissions($cashier), as: $cashier)->assertForbidden();

    expect($this->store->run(static fn (): int => $cashier->permissions()->count()))->toBe(0);
});
