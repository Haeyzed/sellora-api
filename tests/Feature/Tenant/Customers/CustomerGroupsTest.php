<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Customer groups (section 10, built with Promotions in Step 11): staff
 * create groups and put customers in them by hand, with customers.view and
 * customers.manage. There are no rule-based segments and no default group.
 * Group changes are audited; who was added or removed is in the customer's
 * activity log by public ID only. Erasure takes a customer out of every group.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer, $this->outsider] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $support = Role::findOrCreate('Support', StaffMember::GUARD);
        $support->givePermissionTo(Permission::findByName('customers.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($support);

        return [$owner, $viewer, StaffMember::factory()->create()];
    });

    $this->customer = firstStoreCustomer(['name' => 'Ada Lovelace']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('creates, lists by name, searches, shows, renames and deletes groups, each audited', function (): void {
    $wholesale = customerGroupRequest('POST', '', ['name' => ' Wholesale ', 'description' => 'Trade buyers'], $this->owner)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Wholesale')
        ->assertJsonPath('data.members_count', 0)
        ->json('data.id');
    customerGroupRequest('POST', '', ['name' => 'VIP'], $this->owner)->assertCreated();

    customerGroupRequest('GET', '', as: $this->viewer)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.name', 'VIP');
    customerGroupRequest('GET', '?search=whole', as: $this->viewer)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wholesale);
    customerGroupRequest('GET', "/{$wholesale}", as: $this->viewer)->assertOk()->assertJsonPath('data.description', 'Trade buyers');

    customerGroupRequest('PATCH', "/{$wholesale}", ['name' => 'Trade', 'description' => null], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.name', 'Trade')
        ->assertJsonPath('data.description', null);
    customerGroupRequest('DELETE', "/{$wholesale}", as: $this->owner)->assertNoContent();
    customerGroupRequest('GET', "/{$wholesale}", as: $this->viewer)->assertNotFound();

    $events = $this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'customer_group')->orderBy('id')->pluck('event')->all());
    expect($events)->toBe(['created', 'created', 'updated', 'deleted']);
});

it('keeps group names unique ignoring case', function (): void {
    customerGroupRequest('POST', '', ['name' => 'Wholesale'], $this->owner)->assertCreated();
    $vip = customerGroupRequest('POST', '', ['name' => 'VIP'], $this->owner)->json('data.id');

    customerGroupRequest('POST', '', ['name' => 'WHOLESALE'], $this->owner)->assertUnprocessable()->assertJsonPath('code', 'customer_group_name_taken')->assertJsonValidationErrors('name');
    customerGroupRequest('PATCH', "/{$vip}", ['name' => 'wholesale'], $this->owner)->assertUnprocessable()->assertJsonPath('code', 'customer_group_name_taken');
});

it('puts customers in a group once each, lists them, takes them out, and logs it by public ID only', function (): void {
    $grace = firstStoreCustomer(['email' => 'grace@example.com', 'name' => 'Grace Hopper']);
    $group = customerGroupRequest('POST', '', ['name' => 'VIP'], $this->owner)->json('data.id');

    customerGroupRequest('POST', "/{$group}/members", ['customers' => [$this->customer->public_id, $grace->public_id]], $this->owner)->assertOk()->assertExactJson(['added' => 2]);
    customerGroupRequest('POST', "/{$group}/members", ['customers' => [$this->customer->public_id]], $this->owner)->assertOk()->assertExactJson(['added' => 0]);

    customerGroupRequest('GET', "/{$group}/members", as: $this->viewer)->assertOk()->assertJsonCount(2, 'data');
    customerGroupRequest('GET', "/{$group}", as: $this->viewer)->assertJsonPath('data.members_count', 2);
    staffCustomerRequest('GET', "?group={$group}", as: $this->viewer)->assertOk()->assertJsonCount(2, 'data');

    customerGroupRequest('DELETE', "/{$group}/members/{$grace->public_id}", as: $this->owner)->assertNoContent();
    // Taking out someone who isn't in the group changes nothing, so it isn't logged.
    customerGroupRequest('DELETE', "/{$group}/members/{$grace->public_id}", as: $this->owner)->assertNoContent();
    staffCustomerRequest('GET', "?group={$group}", as: $this->viewer)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->customer->public_id);

    $entries = $this->store->run(static fn (): array => Activity::query()->inLog('customers')->orderBy('id')->get(['event', 'description', 'subject_id'])->toArray());
    expect(array_column($entries, 'event'))->toBe(['customer_group_member_added', 'customer_group_member_added', 'customer_group_member_removed'])
        ->and($entries[2]['description'])->toBe("Removed from group {$group}: customer {$grace->public_id}")
        ->and(json_encode($entries))->not->toContain('Grace')->not->toContain('grace@example.com');
});

it('refuses customers in the trash, erased or unknown', function (): void {
    $group = customerGroupRequest('POST', '', ['name' => 'VIP'], $this->owner)->json('data.id');
    $trashed = $this->store->run(static function (): string {
        $customer = Customer::factory()->create();
        $customer->delete();

        return $customer->public_id;
    });

    customerGroupRequest('POST', "/{$group}/members", ['customers' => [$trashed]], $this->owner)->assertJsonValidationErrors('customers.0');
    customerGroupRequest('POST', "/{$group}/members", ['customers' => ['01JUNKJUNKJUNKJUNKJUNKJUNK']], $this->owner)->assertJsonValidationErrors('customers.0');
    customerGroupRequest('POST', "/{$group}/members", ['customers' => []], $this->owner)->assertJsonValidationErrors('customers');
});

it('needs customers.view to see groups and customers.manage to change them', function (string $method, string $path, StaffMemberKind $as): void {
    $group = $this->store->run(static fn (): string => CustomerGroup::factory()->create()->public_id);
    $path = str_replace(['{group}', '{customer}'], [$group, $this->customer->public_id], $path);

    customerGroupRequest($method, $path, ['name' => 'Mallory', 'customers' => [$this->customer->public_id]], $as === StaffMemberKind::Viewer ? $this->viewer : $this->outsider)->assertForbidden();
})->with([
    'list, without customers.view' => ['GET', '', StaffMemberKind::Outsider],
    'show, without customers.view' => ['GET', '/{group}', StaffMemberKind::Outsider],
    'members, without customers.view' => ['GET', '/{group}/members', StaffMemberKind::Outsider],
    'create, with view only' => ['POST', '', StaffMemberKind::Viewer],
    'rename, with view only' => ['PATCH', '/{group}', StaffMemberKind::Viewer],
    'delete, with view only' => ['DELETE', '/{group}', StaffMemberKind::Viewer],
    'add members, with view only' => ['POST', '/{group}/members', StaffMemberKind::Viewer],
    'remove a member, with view only' => ['DELETE', '/{group}/members/{customer}', StaffMemberKind::Viewer],
]);

it('exports the customer\'s groups, and erasure takes them out of every group but keeps the groups', function (): void {
    $group = $this->store->run(function (): CustomerGroup {
        $group = CustomerGroup::factory()->create(['name' => 'VIP']);
        $group->members()->attach($this->customer->id);

        return $group;
    });

    $export = $this->store->run(fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->customer)));
    expect($export['customer_groups'])->toBe([['id' => $group->public_id, 'name' => 'VIP']]);

    $this->store->run(fn () => app(EraseCustomer::class)->erase($this->customer));

    expect($this->store->run(static fn (): array => [CustomerGroup::query()->count(), DB::table('customer_group_members')->count()]))->toBe([1, 0]);
});

it('never shows one store\'s groups to another store\'s staff', function (): void {
    $group = customerGroupRequest('POST', '', ['name' => 'VIP'], $this->owner)->json('data.id');
    $otherStore = createStore('second-store');
    $theirOwner = $otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    customerGroupRequest('GET', '', as: $theirOwner, subdomain: 'second-store')->assertOk()->assertJsonCount(0, 'data');
    customerGroupRequest('GET', "/{$group}", as: $theirOwner, subdomain: 'second-store')->assertNotFound();
});
