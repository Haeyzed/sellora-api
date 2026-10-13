<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerAddress;
use App\Tenant\Customers\Models\CustomerNote;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Staff managing the store's customers (section 10): with customers.view
 * they see accounts, addresses and notes; with customers.manage they
 * correct profiles (never the email), deactivate, trash and restore accounts
 * and write notes. Staff never create customers. The activity log names the
 * customer by public ID only, never their details.
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

    $this->customer = firstStoreCustomer(['name' => 'Ada Lovelace', 'phone' => '+2348031234567']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists customers newest first, searched by name, email or phone in any case, and narrowed by status', function (): void {
    firstStoreCustomer(['email' => 'grace@example.com', 'name' => 'Grace Hopper', 'is_active' => false, 'email_verified_at' => now()]);
    $this->store->run(static fn () => Customer::factory()->create(['email' => 'trashed@example.com', 'name' => 'Trashed Person'])->delete());

    $emails = fn (string $query = ''): array => array_column(staffCustomerRequest('GET', $query, as: $this->viewer)->assertOk()->json('data'), 'email');

    expect($emails())->toBe(['grace@example.com', 'ada@example.com'])
        ->and($emails('?search=LOVE'))->toBe(['ada@example.com'])
        ->and($emails('?search=grace%20example'))->toBe(['grace@example.com'])
        ->and($emails('?search=803123'))->toBe(['ada@example.com'])
        ->and($emails('?is_active=0'))->toBe(['grace@example.com'])
        ->and($emails('?email_verified=1'))->toBe(['grace@example.com'])
        ->and($emails('?trashed=1'))->toBe(['trashed@example.com']);
});

it('shows a customer, their addresses and notes to staff who may view customers', function (): void {
    $this->store->run(fn () => CustomerAddress::factory()->for($this->customer)->create(['label' => 'Home']));
    staffCustomerRequest('POST', "/{$this->customer->public_id}/notes", ['body' => 'Prefers deliveries after 5pm.'], $this->owner)->assertCreated();

    staffCustomerRequest('GET', "/{$this->customer->public_id}", as: $this->viewer)
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.search_text');
    staffCustomerRequest('GET', "/{$this->customer->public_id}/addresses", as: $this->viewer)->assertOk()->assertJsonPath('data.0.label', 'Home');
    staffCustomerRequest('GET', "/{$this->customer->public_id}/notes", as: $this->viewer)
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Prefers deliveries after 5pm.')
        ->assertJsonPath('data.0.author.id', $this->owner->public_id);
});

it('refuses every customer endpoint to staff without the matching permission', function (string $method, string $path, StaffMemberKind $as): void {
    $path = str_replace('{customer}', $this->customer->public_id, $path);

    staffCustomerRequest($method, $path, ['name' => 'Mallory', 'body' => 'x'], $as === StaffMemberKind::Viewer ? $this->viewer : $this->outsider)->assertForbidden();
})->with([
    'list, without customers.view' => ['GET', '', StaffMemberKind::Outsider],
    'show, without customers.view' => ['GET', '/{customer}', StaffMemberKind::Outsider],
    'addresses, without customers.view' => ['GET', '/{customer}/addresses', StaffMemberKind::Outsider],
    'notes, without customers.view' => ['GET', '/{customer}/notes', StaffMemberKind::Outsider],
    'update, with view only' => ['PATCH', '/{customer}', StaffMemberKind::Viewer],
    'deactivate, with view only' => ['POST', '/{customer}/deactivate', StaffMemberKind::Viewer],
    'reactivate, with view only' => ['POST', '/{customer}/reactivate', StaffMemberKind::Viewer],
    'trash, with view only' => ['DELETE', '/{customer}', StaffMemberKind::Viewer],
    'restore, with view only' => ['POST', '/{customer}/restore', StaffMemberKind::Viewer],
    'add a note, with view only' => ['POST', '/{customer}/notes', StaffMemberKind::Viewer],
]);

it('lets staff correct the name, phone and language, but never the email', function (): void {
    staffCustomerRequest('PATCH', "/{$this->customer->public_id}", ['name' => 'Ada King', 'phone' => '0803 765 4321', 'email' => 'mallory@example.com'], $this->owner)
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada King')
        ->assertJsonPath('data.phone', '+2348037654321')
        ->assertJsonPath('data.email', 'ada@example.com');
});

it('records what staff did in the activity log without the customer\'s details', function (): void {
    $id = $this->customer->public_id;
    staffCustomerRequest('PATCH', "/{$id}", ['name' => 'Ada King', 'phone' => '0803 765 4321'], $this->owner)->assertOk();
    staffCustomerRequest('POST', "/{$id}/deactivate", as: $this->owner)->assertOk();
    $note = staffCustomerRequest('POST', "/{$id}/notes", ['body' => 'Asked to be called back.'], $this->owner)->json('data.id');
    staffCustomerRequest('DELETE', "/{$id}/notes/{$note}", as: $this->owner)->assertNoContent();

    $entries = $this->store->run(static fn (): array => Activity::query()->inLog('customers')->orderBy('id')->get()
        ->map(static fn (Activity $entry): array => $entry->only(['event', 'description', 'causer_type', 'causer_id', 'subject_type', 'properties']))
        ->all());

    expect(array_column($entries, 'event'))->toBe(['customer_updated', 'customer_deactivated', 'customer_note_added', 'customer_note_deleted'])
        ->and($entries[0]['properties']->toArray())->toBe(['fields' => ['name', 'phone']])
        ->and(array_unique(array_column($entries, 'causer_id')))->toBe([$this->owner->id])
        ->and(array_unique(array_column($entries, 'subject_type')))->toBe(['customer'])
        ->and(array_filter($entries, static fn (array $entry): bool => ! str_ends_with($entry['description'], "customer {$id}")))->toBe([])
        ->and(json_encode($entries))->not->toContain('Ada')->not->toContain('ada@example.com')->not->toContain('803')->not->toContain('called back');
});

it('signs a deactivated customer out everywhere, and lets them back in once reactivated', function (): void {
    $token = customerTokenFor($this->customer);

    staffCustomerRequest('POST', "/{$this->customer->public_id}/deactivate", as: $this->owner)->assertOk()->assertJsonPath('data.is_active', false);
    customerRequest('GET', 'auth/me', as: $token)->assertUnauthorized();
    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/tokens'), ['email' => 'ada@example.com', 'password' => 'a-long-shopper-password'])->assertForbidden();
    tenancy()->end();

    staffCustomerRequest('POST', "/{$this->customer->public_id}/reactivate", as: $this->owner)->assertOk()->assertJsonPath('data.is_active', true);
    // The old device's token was revoked, not just refused while deactivated.
    customerRequest('GET', 'auth/me', as: $token)->assertUnauthorized();
    $this->postJson(storeUrl('first-store', '/api/v1/customer/auth/tokens'), ['email' => 'ada@example.com', 'password' => 'a-long-shopper-password'])->assertOk();

    $audited = $this->store->run(fn () => Audit::query()->where('auditable_type', 'customer')->where('auditable_id', $this->customer->id)->get());
    expect($audited->pluck('new_values')->all())->toBe([['is_active' => false], ['is_active' => true]]);
});

it('trashes a customer, signing them out, and restores them', function (): void {
    $token = customerTokenFor($this->customer);

    staffCustomerRequest('DELETE', "/{$this->customer->public_id}", as: $this->owner)->assertNoContent();
    customerRequest('GET', 'auth/me', as: $token)->assertUnauthorized();
    staffCustomerRequest('GET', "/{$this->customer->public_id}", as: $this->viewer)->assertOk()->assertJsonPath('data.deleted_at', fn (?string $at): bool => $at !== null);

    staffCustomerRequest('POST', "/{$this->customer->public_id}/restore", as: $this->owner)->assertOk()->assertJsonPath('data.deleted_at', null);
    // The old device's token was revoked, not just unusable while in the trash.
    customerRequest('GET', 'auth/me', as: $token)->assertUnauthorized();
});

it('deletes a note only through its own customer', function (): void {
    $other = firstStoreCustomer(['email' => 'grace@example.com']);
    $note = staffCustomerRequest('POST', "/{$other->public_id}/notes", ['body' => 'Grace\'s note.'], $this->owner)->json('data.id');

    staffCustomerRequest('DELETE', "/{$this->customer->public_id}/notes/{$note}", as: $this->owner)->assertNotFound();
    expect($this->store->run(static fn (): int => CustomerNote::query()->count()))->toBe(1);
});

it('offers staff no way to create a customer', function (): void {
    staffCustomerRequest('POST', '', ['name' => 'Ada', 'email' => 'new@example.com'], $this->owner)->assertMethodNotAllowed();
});

it('keeps the staff customer routes to the staff guard', function (): void {
    $customerToken = customerTokenFor($this->customer);

    staffCustomerRequest('GET', '', as: $customerToken)->assertUnauthorized();
    staffCustomerRequest('GET', "/{$this->customer->public_id}", as: $customerToken)->assertUnauthorized();
});

it('never shows one store\'s customers to another store\'s staff', function (): void {
    $otherStore = createStore('second-store');
    $theirOwner = $otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    staffCustomerRequest('GET', '', as: $theirOwner, subdomain: 'second-store')->assertOk()->assertJsonCount(0, 'data');
    staffCustomerRequest('GET', "/{$this->customer->public_id}", as: $theirOwner, subdomain: 'second-store')->assertNotFound();
});
