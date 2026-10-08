<?php

declare(strict_types=1);

use App\Landlord\Identity\Enums\PlatformPermission;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffInvitationNotification;
use App\Tenant\Identity\StaffPermissionCatalogue;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
 * Section 10 and 14: staff join only by invitation and choose their own
 * password; nobody can grant permissions they don't hold, manage someone more
 * powerful, or change their own roles; the owner is untouchable here; the
 * plan's staff limit counts active staff and pending invitations; and nothing
 * (links, role IDs) crosses from one store to another.
 */
uses(DatabaseTruncation::class);

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

function allowStaffAccounts(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['staff_accounts' => $limit]));
    app(App\Shared\Features\Features::class)->forget($tenantId);
}

/**
 * Sends a request to a store's team API as the given staff member, or with no token.
 */
function team(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'first-store'): TestResponse
{
    $request = test();

    if ($as !== null) {
        $request = $request->withToken(staffTokenFor($as, $subdomain));
    }

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function staffTokenFor(StaffMember $staffMember, string $subdomain): string
{
    $store = App\Landlord\Tenancy\Models\Tenant::query()
        ->whereHas('domains', static fn ($query) => $query->where('domain', $subdomain.'.'.config('platform.domain')))
        ->firstOrFail();

    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($staffMember, StaffMember::GUARD, 'test')->plainTextToken);
}

/**
 * A role in the first store with exactly these permissions, created directly.
 *
 * @param  list<string>  $permissions
 */
function roleWith(string $name, array $permissions): Role
{
    return test()->store->run(static function () use ($name, $permissions): Role {
        $role = Role::findOrCreate($name, StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (string $permission) => Spatie\Permission\Models\Permission::findOrCreate($permission, StaffMember::GUARD), $permissions));

        return $role;
    });
}

/**
 * A staff member of the first store holding the given roles.
 */
function colleagueWith(string $email, Role ...$roles): StaffMember
{
    return test()->store->run(static function () use ($email, $roles): StaffMember {
        $staffMember = StaffMember::factory()->create(['email' => $email, 'password' => TEAM_PASSWORD]);
        $staffMember->syncRoles($roles);

        return $staffMember;
    });
}

/**
 * Invites someone as the owner and returns the token from the emailed link.
 *
 * @param  list<string>  $roleIds
 */
function inviteAndCaptureToken(string $email, array $roleIds = []): string
{
    team('POST', 'team/invitations', ['email' => $email, 'roles' => $roleIds], test()->owner)->assertCreated();

    $token = null;
    Notification::assertSentOnDemand(StaffInvitationNotification::class, static function (StaffInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token): bool {
        if ($notifiable->routeNotificationFor('mail') !== $email) {
            return false;
        }

        parse_str((string) parse_url($notification->acceptUrl(), PHP_URL_QUERY), $query);
        $token = is_string($query['token'] ?? null) ? $query['token'] : null;

        return true;
    });

    return (string) $token;
}

it('invites by email without creating an account, and the person joins with their own password and roles', function (): void {
    $cashier = roleWith('Cashier', []);

    $invitation = team('POST', 'team/invitations', ['email' => ' Sam@Example.com', 'name' => 'Sam', 'roles' => [$cashier->public_id]], $this->owner)
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
        parse_str((string) parse_url($notification->acceptUrl(), PHP_URL_QUERY), $query);
        $token = (string) $query['token'];

        return str_starts_with($notification->acceptUrl(), 'https://first-store.'.config('platform.domain').'/admin/accept-invitation?token=');
    });
    expect($this->store->run(static fn (): string => StaffInvitation::query()->sole()->token_hash))->toBe(hash('sha256', $token));

    team('POST', 'auth/invitation-previews', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.email', 'sam@example.com')
        ->assertJsonPath('data.roles', ['Cashier']);

    $accessToken = team('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Sam Smith', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
        ->assertCreated()
        ->json('data.token');

    $this->withToken($accessToken)->getJson(storeUrl('first-store', '/api/v1/staff/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'sam@example.com')
        ->assertJsonPath('data.name', 'Sam Smith')
        ->assertJsonPath('data.roles', ['Cashier']);
    tenancy()->end();

    team('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Someone Else', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'staff_invitation_invalid');
    expect($invitation['id'])->toBeString();
});

it('refuses expired, cancelled and unknown links with the same error', function (): void {
    $expiredToken = inviteAndCaptureToken('late@example.com');
    $this->travel(8)->days();
    $cancelledToken = inviteAndCaptureToken('cancelled@example.com');
    $cancelled = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->where('email', 'cancelled@example.com')->sole());
    team('DELETE', 'team/invitations/'.$cancelled->public_id, as: $this->owner)->assertNoContent();

    foreach ([$expiredToken, $cancelledToken, str_repeat('x', 64)] as $token) {
        team('POST', 'auth/invitation-acceptances', ['token' => $token, 'name' => 'Late', 'password' => TEAM_PASSWORD, 'password_confirmation' => TEAM_PASSWORD])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'staff_invitation_invalid');
    }

    team('DELETE', 'team/invitations/'.$cancelled->public_id, as: $this->owner)->assertConflict()->assertJsonPath('code', 'staff_invitation_not_pending');
});

it('refuses to invite a current staff member or someone already invited, and resending replaces the link', function (): void {
    team('POST', 'team/invitations', ['email' => 'owner@example.com', 'roles' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'staff_member_already_exists');

    $firstToken = inviteAndCaptureToken('sam@example.com');
    team('POST', 'team/invitations', ['email' => 'SAM@example.com', 'roles' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'staff_invitation_already_pending');

    $invitation = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->sole());
    Notification::fake();
    team('POST', 'team/invitations/'.$invitation->public_id.'/resend', as: $this->owner)->assertOk();
    Notification::assertSentOnDemandTimes(StaffInvitationNotification::class, 1);

    team('POST', 'auth/invitation-previews', ['token' => $firstToken])->assertUnprocessable()->assertJsonPath('code', 'staff_invitation_invalid');
});

it('counts active staff and pending invitations towards the plan\'s staff limit, but not the owner or deactivated staff', function (): void {
    // One place beyond the owner, who never counts.
    allowStaffAccounts($this->store->getTenantKey(), 1);

    team('POST', 'team/invitations', ['email' => 'first@example.com', 'roles' => []], $this->owner)->assertCreated();
    team('POST', 'team/invitations', ['email' => 'second@example.com', 'roles' => []], $this->owner)
        ->assertForbidden()
        ->assertJsonPath('code', 'usage_limit_reached');

    $first = $this->store->run(static fn (): StaffInvitation => StaffInvitation::query()->sole());
    team('DELETE', 'team/invitations/'.$first->public_id, as: $this->owner)->assertNoContent();
    team('POST', 'team/invitations', ['email' => 'second@example.com', 'roles' => []], $this->owner)->assertCreated();

    $deactivated = $this->store->run(static fn (): StaffMember => StaffMember::factory()->deactivated()->create());
    team('POST', 'team/members/'.$deactivated->public_id.'/reactivate', as: $this->owner)->assertForbidden()->assertJsonPath('code', 'usage_limit_reached');
});

it('never lets staff grant permissions they don\'t hold, through a role or an invitation', function (): void {
    $roleEditor = colleagueWith('editor@example.com', roleWith('Role editor', ['roles.view', 'roles.manage', 'staff.invite']));
    $powerful = roleWith('Powerful', ['staff.manage']);

    team('POST', 'team/roles', ['name' => 'Sneaky', 'permissions' => ['roles.manage', 'staff.manage']], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');
    team('PUT', 'team/roles/'.$powerful->public_id, ['name' => 'Powerful', 'permissions' => []], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');
    team('POST', 'team/invitations', ['email' => 'friend@example.com', 'roles' => [$powerful->public_id]], $roleEditor)
        ->assertForbidden()
        ->assertJsonPath('code', 'permissions_exceed_your_own');

    team('POST', 'team/roles', ['name' => 'Helper', 'permissions' => ['roles.view']], $roleEditor)->assertCreated();
});

it('never gives the Owner role by invitation or role change', function (): void {
    $ownerRole = $this->store->run(static fn (): Role => Role::findByName(StaffRole::Owner->value, StaffMember::GUARD));
    $colleague = colleagueWith('sam@example.com');

    team('POST', 'team/invitations', ['email' => 'friend@example.com', 'roles' => [$ownerRole->public_id]], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
    team('PUT', 'team/members/'.$colleague->public_id.'/roles', ['roles' => [$ownerRole->public_id]], $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
});

it('never lets staff manage themselves, the owner, or someone more powerful', function (): void {
    $manager = colleagueWith('manager@example.com', roleWith('Manager', ['staff.view', 'staff.manage']));
    $supervisor = colleagueWith('supervisor@example.com', roleWith('Supervisor', ['staff.view', 'staff.manage', 'roles.manage']));

    team('PUT', 'team/members/'.$manager->public_id.'/roles', ['roles' => []], $manager)->assertForbidden()->assertJsonPath('code', 'cannot_manage_own_account');
    team('POST', 'team/members/'.$this->owner->public_id.'/deactivate', as: $manager)->assertForbidden()->assertJsonPath('code', 'store_owner_protected');
    team('POST', 'team/members/'.$supervisor->public_id.'/deactivate', as: $manager)->assertForbidden()->assertJsonPath('code', 'permissions_exceed_your_own');
});

it('deactivates a colleague, signing them out at once, and reactivates them with their roles', function (): void {
    $cashierRole = roleWith('Cashier', []);
    $colleague = colleagueWith('sam@example.com', $cashierRole);
    $colleagueToken = staffTokenFor($colleague, 'first-store');

    team('POST', 'team/members/'.$colleague->public_id.'/deactivate', as: $this->owner)->assertOk()->assertJsonPath('data.is_active', false);
    $this->withToken($colleagueToken)->getJson(storeUrl('first-store', '/api/v1/staff/auth/me'))->assertUnauthorized();
    tenancy()->end();

    team('POST', 'team/members/'.$colleague->public_id.'/reactivate', as: $this->owner)
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

    team('PUT', 'team/members/'.$colleague->public_id.'/roles', ['roles' => [$viewer->public_id]], $this->owner)->assertOk()->assertJsonPath('data.roles.0.name', 'Team viewer');

    $this->withToken($colleagueToken)->getJson(storeUrl('first-store', '/api/v1/staff/team/members'))->assertOk()->assertJsonCount(2, 'data');
});

it('refuses every team endpoint to staff without the matching permission', function (string $method, string $path): void {
    $nobody = colleagueWith('nobody@example.com');
    $colleague = colleagueWith('sam@example.com');
    $role = roleWith('Cashier', []);
    $path = strtr($path, ['{member}' => $colleague->public_id, '{role}' => $role->public_id]);

    team($method, $path, ['email' => 'x@example.com', 'name' => 'Some role', 'roles' => [], 'permissions' => []], $nobody)->assertForbidden();
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

    $role = team('POST', 'team/roles', ['name' => 'Cashier', 'permissions' => ['staff.view']], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.permissions', ['staff.view'])
        ->json('data');

    team('POST', 'team/roles', ['name' => 'cashier', 'permissions' => []], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('code', 'role_name_taken');
    team('POST', 'team/roles', ['name' => 'OWNER', 'permissions' => []], $this->owner)->assertJsonPath('code', 'role_name_taken');
    team('POST', 'team/roles', ['name' => 'Typo', 'permissions' => ['staff.everything']], $this->owner)->assertJsonValidationErrors('permissions.0');
    team('PUT', 'team/roles/'.$ownerRole->public_id, ['name' => 'Boss', 'permissions' => []], $this->owner)->assertConflict()->assertJsonPath('code', 'role_protected');
    team('DELETE', 'team/roles/'.$ownerRole->public_id, as: $this->owner)->assertConflict()->assertJsonPath('code', 'role_protected');

    team('PUT', 'team/roles/'.$role['id'], ['name' => 'Senior cashier', 'permissions' => ['staff.view', 'roles.view']], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.name', 'Senior cashier')
        ->assertJsonPath('data.permissions', ['roles.view', 'staff.view']);

    $audits = $this->store->run(static fn (): array => Audit::query()->orderBy('id')->get(['event', 'old_values', 'new_values'])->toArray());
    expect(array_column($audits, 'event'))->toContain('updated', 'sync')
        ->and(json_encode($audits))->toContain('roles.view');

    colleagueWith('sam@example.com', $this->store->run(static fn (): Role => Role::findByName('Senior cashier', StaffMember::GUARD)));
    team('DELETE', 'team/roles/'.$role['id'], as: $this->owner)->assertConflict()->assertJsonPath('code', 'role_in_use');

    team('GET', 'team/roles', as: $this->owner)
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
    team('GET', 'team/permissions', as: $this->owner)
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

    team('POST', 'auth/invitation-previews', ['token' => $token], subdomain: 'second-store')->assertUnprocessable()->assertJsonPath('code', 'staff_invitation_invalid');
    team('POST', 'team/invitations', ['email' => 'x@example.com', 'roles' => [$secondStoreRole->public_id]], $this->owner)->assertJsonValidationErrors('roles.0');
    team('GET', 'team/members', as: $secondOwner, subdomain: 'second-store')->assertOk()->assertJsonCount(1, 'data');
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
