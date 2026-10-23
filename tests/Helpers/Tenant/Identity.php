<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Jobs\SyncStoreOwnerContact;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For OwnershipTransferTest.php
|--------------------------------------------------------------------------
*/

/**
 * Sends a request to a store's ownership transfer API as the given staff member.
 */
function ownershipTransfer(string $method, string $path, array $data, StaffMember $as, string $subdomain = 'owned-store'): TestResponse
{
    forgetSignIns();
    $store = Tenant::query()->whereHas('domains', static fn ($query) => $query->where('domain', Tenant::platformDomainFor($subdomain)))->firstOrFail();
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/team/ownership-transfers'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The owner's offer to the colleague, keeping the given roles.
 *
 * @param  list<string>  $keptRoleIds
 */
function offerStoreToColleague(array $keptRoleIds = [], array $extra = []): TestResponse
{
    return ownershipTransfer('POST', '', [
        'staff_member' => test()->colleague->public_id,
        'current_password' => OWNERSHIP_PASSWORD,
        'roles' => $keptRoleIds,
        ...$extra,
    ], test()->owner);
}

function acceptStore(string $transferId, ?StaffMember $as = null, ?string $termsOfServiceId = null): TestResponse
{
    return ownershipTransfer('POST', "/{$transferId}/acceptance", [
        'accepted_terms_of_service' => $termsOfServiceId ?? test()->termsOfService->public_id,
    ], $as ?? test()->colleague);
}

/**
 * @return array{owner: bool, colleague: bool, owner_roles: list<string>, colleague_roles: list<string>}
 */
function storeOwnershipState(): array
{
    return test()->store->run(static function (): array {
        $owner = StaffMember::query()->findOrFail(test()->owner->id);
        $colleague = StaffMember::query()->findOrFail(test()->colleague->id);

        return [
            'owner' => $owner->hasRole(StaffRole::Owner->value),
            'colleague' => $colleague->hasRole(StaffRole::Owner->value),
            'owner_roles' => array_values($owner->getRoleNames()->all()),
            'colleague_roles' => array_values($colleague->getRoleNames()->all()),
        ];
    });
}

/**
 * Runs the queued job that brings the platform up to date, in the store's context, as a queue worker would.
 */
function runOwnerContactSync(SyncStoreOwnerContact $job): void
{
    test()->store->run(static fn () => app()->call([$job, 'handle']));
    tenancy()->end();
}

/*
|--------------------------------------------------------------------------
| For StaffAuthTest.php
|--------------------------------------------------------------------------
*/

function signInAsStaff(string $subdomain, string $email = 'owner@first-store.test'): TestResponse
{
    $response = test()->postJson(storeUrl($subdomain, '/api/v1/staff/auth/tokens'), ['email' => $email, 'password' => STAFF_PASSWORD]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For StaffTwoFactorTest.php
|--------------------------------------------------------------------------
*/

function staffApiRequest(string $subdomain, string $method, string $path, array $data = [], ?string $token = null): TestResponse
{
    $request = $token === null ? test() : test()->withToken($token);
    $response = $request->json($method, storeUrl($subdomain, '/api/v1/staff/auth/'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For TeamManagementTest.php
|--------------------------------------------------------------------------
*/

function allowStaffAccounts(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['staff_accounts' => $limit]));
    app(App\Shared\Features\Features::class)->forget($tenantId);
}

/**
 * Sends a request to a store's team API as the given staff member, or with no token.
 */
function teamRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'first-store'): TestResponse
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
    $store = Tenant::query()
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
    teamRequest('POST', 'team/invitations', ['email' => $email, 'roles' => $roleIds], test()->owner)->assertCreated();

    $token = null;
    Notification::assertSentOnDemand(StaffInvitationNotification::class, static function (StaffInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token): bool {
        if ($notifiable->routeNotificationFor('mail') !== $email) {
            return false;
        }

        $token = emailedLinkFragment($notification->acceptUrl())['token'] ?? null;

        return true;
    });

    return (string) $token;
}
