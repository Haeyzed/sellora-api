<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/*
 * Section 10: two-factor authentication is a store setting for staff. Only
 * the owner can require it, with their password and their own two-factor
 * code. While it is required, staff who haven't set it up can only see
 * their profile, set it up and sign out. Customers and drivers are never
 * affected.
 */
uses(DatabaseTruncation::class);

const TWO_FACTOR_OWNER_PASSWORD = 'a-long-two-factor-owner-password';

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('secured-store');

    [$this->owner, $this->colleague, $this->securedColleague, $this->customer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create(['password' => TWO_FACTOR_OWNER_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        $teamViewer = Role::findOrCreate('Team viewer', StaffMember::GUARD);
        $teamViewer->syncPermissions([
            Permission::findOrCreate('staff.view', StaffMember::GUARD),
            Permission::findOrCreate('settings.view', StaffMember::GUARD),
            Permission::findOrCreate('settings.manage', StaffMember::GUARD),
        ]);
        $colleague = StaffMember::factory()->create();
        $colleague->assignRole($teamViewer);
        $securedColleague = StaffMember::factory()->create();
        $securedColleague->assignRole($teamViewer);
        enableTwoFactor($securedColleague);

        return [$owner, $colleague, $securedColleague, Customer::factory()->create()];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * Sends a request to the store's API as the given account.
 *
 * @param  array<string, mixed>  $data
 */
function securedStoreRequest(string $method, string $path, User $as, string $guard = StaffMember::GUARD, array $data = []): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as, $guard, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl('secured-store', '/api/v1'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * @param  array<string, mixed>  $data
 */
function requireStaffTwoFactor(bool $required, array $data = [], ?StaffMember $as = null): TestResponse
{
    return securedStoreRequest('PUT', '/staff/store/settings/staff-two-factor', $as ?? test()->owner, data: ['required' => $required, 'current_password' => TWO_FACTOR_OWNER_PASSWORD, ...$data]);
}

function staffTwoFactorIsRequired(): bool
{
    return test()->store->run(static fn (): bool => app(FindStoreSettings::class)->handle()->require_staff_two_factor);
}

it('lets only the owner require it, with their password and a code, once they use it themselves', function (): void {
    requireStaffTwoFactor(true, as: $this->colleague)->assertForbidden();
    requireStaffTwoFactor(true)->assertUnprocessable()->assertJsonPath('code', 'own_two_factor_required');
    expect(staffTwoFactorIsRequired())->toBeFalse();

    $secret = $this->store->run(fn (): string => enableTwoFactor(StaffMember::query()->findOrFail($this->owner->id)));

    requireStaffTwoFactor(true, ['current_password' => 'not-the-password', 'code' => twoFactorCode($secret)])->assertUnprocessable()->assertJsonPath('code', 'current_password_incorrect');
    requireStaffTwoFactor(true)->assertUnprocessable()->assertJsonPath('code', 'two_factor_code_invalid');
    expect(staffTwoFactorIsRequired())->toBeFalse();

    requireStaffTwoFactor(true, ['code' => twoFactorCode($secret)])->assertOk()->assertJsonPath('data.require_staff_two_factor', true);
    expect(staffTwoFactorIsRequired())->toBeTrue();
});

it('audits the change and logs it, and can turn it off again', function (): void {
    $secret = $this->store->run(fn (): string => enableTwoFactor(StaffMember::query()->findOrFail($this->owner->id)));

    requireStaffTwoFactor(true, ['code' => twoFactorCode($secret)])->assertOk();
    requireStaffTwoFactor(false, ['code' => twoFactorCode($secret, stepsFromNow: 1)])->assertOk()->assertJsonPath('data.require_staff_two_factor', false);

    $this->store->run(function (): void {
        expect(Activity::query()->where('causer_id', $this->owner->id)->pluck('event')->all())->toBe(['staff_two_factor_required', 'staff_two_factor_no_longer_required'])
            ->and(Audit::query()->where('auditable_type', 'store_settings')->where('event', 'updated')->get()->map(static fn (Audit $audit): mixed => $audit->new_values['require_staff_two_factor'] ?? null)->all())->toBe([true, false]);
    });
});

it('leaves staff without two-factor authentication only their profile, setting it up and signing out while it is required', function (): void {
    securedStoreRequest('GET', '/staff/team/members', $this->colleague)->assertOk();

    $this->store->run(static fn () => app(FindStoreSettings::class)->handle()->forceFill(['require_staff_two_factor' => true])->save());

    securedStoreRequest('GET', '/staff/team/members', $this->colleague)->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    securedStoreRequest('GET', '/staff/store/settings', $this->colleague)->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    securedStoreRequest('PUT', '/staff/auth/password', $this->colleague)->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    securedStoreRequest('GET', '/staff/auth/me', $this->colleague)->assertOk();
    securedStoreRequest('POST', '/staff/auth/two-factor', $this->colleague)->assertJsonMissing(['code' => 'two_factor_setup_required']);
    securedStoreRequest('DELETE', '/staff/auth/tokens/current', $this->colleague)->assertNoContent();

    securedStoreRequest('GET', '/staff/team/members', $this->securedColleague)->assertOk();
    securedStoreRequest('GET', '/customer/auth/me', $this->customer, Customer::GUARD)->assertOk();
});
