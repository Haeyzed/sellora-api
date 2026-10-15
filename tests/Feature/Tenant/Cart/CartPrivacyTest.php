<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\RetentionRegistry;
use App\Tenant\Cart\CustomerCartRetention;
use App\Tenant\Cart\GuestCartRetention;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Identity\Models\StaffMember;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 12 and section 3.3: a customer's cart is part of their personal data,
 * exported with it and deleted when they are erased. Carts nobody changed
 * for a while are deleted: guests' after 30 days, customers' after 180.
 * Staff with customers.view can see a customer's cart, to help them, but
 * never change it.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    [$this->ada, $this->grace, $this->variant] = $this->store->run(static fn (): array => [
        Customer::factory()->create(['email' => 'ada@example.com']),
        Customer::factory()->create(['email' => 'grace@example.com']),
        cartVariant(name: 'Linen shirt')->public_id,
    ]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets staff with customers.view see a customer\'s cart as the customer does, and never change it', function (): void {
    cartRequest('POST', as: $this->ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $this->variant, 'quantity' => 2], as: $this->ada)->assertOk();
    [$viewer, $outsider] = $this->store->run(static function (): array {
        $role = Role::findOrCreate('Support', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('customers.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        return [$viewer, StaffMember::factory()->create()];
    });
    $path = "/api/v1/staff/customers/{$this->ada->public_id}/cart";

    $response = $this->withToken(staffTokenFor($viewer, 'cart-store'))->getJson(storeUrl('cart-store', $path))->assertOk();
    tenancy()->end();
    expect($response->json('data.lines.0.quantity'))->toBe(2)
        ->and($response->json('data.totals.total.amount'))->toBe(2000)
        ->and($response->json('data'))->not->toHaveKey('token');

    forgetSignIns();
    $this->withToken(staffTokenFor($outsider, 'cart-store'))->getJson(storeUrl('cart-store', $path))->assertForbidden();
    tenancy()->end();
    forgetSignIns();
    $this->withToken(staffTokenFor($viewer, 'cart-store'))->patchJson(storeUrl('cart-store', $path), ['currency' => 'USD'])->assertStatus(405);
    tenancy()->end();
    forgetSignIns();
    $this->withToken(staffTokenFor($viewer, 'cart-store'))->getJson(storeUrl('cart-store', "/api/v1/staff/customers/{$this->grace->public_id}/cart"))->assertNotFound()->assertJsonPath('code', 'cart_not_found');
    tenancy()->end();
    forgetSignIns();
    $this->flushHeaders();
    cartRequest('GET', as: $this->ada)->assertOk();
    $this->withToken(customerTokenFor($this->ada, 'cart-store'))->getJson(storeUrl('cart-store', $path))->assertUnauthorized();
});

it('exports a customer\'s cart with their data, and deletes it when they are erased, leaving everyone else\'s', function (): void {
    cartRequest('POST', as: $this->ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $this->variant, 'quantity' => 3], as: $this->ada)->assertOk();
    cartRequest('POST', as: $this->grace)->assertCreated();
    newGuestCart();

    $this->store->run(function (): void {
        $export = app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->ada))['cart'];

        expect($export)->toMatchArray(['currency' => null, 'tax_country_code' => null, 'promotion_code' => null])
            ->and($export['lines'])->toHaveCount(1)
            ->and($export['lines'][0])->toMatchArray(['product' => 'Linen shirt', 'quantity' => 3])
            ->and(app(PersonalDataRegistry::class)->export(CustomerDataSubject::of(Customer::factory()->create()))['cart'])->toBe([]);

        app(EraseCustomer::class)->erase($this->ada);

        expect(Cart::query()->where('customer_id', $this->ada->id)->exists())->toBeFalse()
            ->and(Cart::query()->count())->toBe(2);
    });
});

it('deletes guest carts after 30 days and customers\' after 180 days without a change, and no sooner', function (): void {
    config(['retention.periods.guest_carts' => 30, 'retention.periods.customer_carts' => 180]);
    newGuestCart();
    cartRequest('POST', as: $this->ada)->assertCreated();

    $this->store->run(function (): void {
        $this->travel(31)->days();
        $recentGuest = Cart::query()->create(['token_hash' => hash('sha256', 'recent'), 'last_activity_at' => CarbonImmutable::now()->subDays(29)]);
        // Made long ago, but changed yesterday: kept, since it is the last change that counts.
        $oldButUsed = Cart::query()->create(['token_hash' => hash('sha256', 'old-but-used'), 'last_activity_at' => CarbonImmutable::now()->subDay()]);
        $oldButUsed->forceFill(['created_at' => CarbonImmutable::now()->subDays(60)])->save();

        expect(app(GuestCartRetention::class)->purgeOlderThan(CarbonImmutable::now()->subDays(30)))->toBe(1)
            ->and(app(CustomerCartRetention::class)->purgeOlderThan(CarbonImmutable::now()->subDays(180)))->toBe(0)
            ->and(Cart::query()->pluck('id')->all())->toContain($recentGuest->id, $oldButUsed->id);

        // Both carts are now over 180 days idle; only the customer's is the customer purge's.
        $this->travel(160)->days();
        Cart::query()->whereKey($oldButUsed->id)->delete();

        expect(app(CustomerCartRetention::class)->purgeOlderThan(CarbonImmutable::now()->subDays(180)))->toBe(1)
            // Only the customer's cart: the guest cart, idle as long, is the guests' purge's to judge.
            ->and(Cart::query()->pluck('id')->all())->toBe([$recentGuest->id])
            ->and(array_map(get_class(...), app(RetentionRegistry::class)->policiesFor(true)))->toContain(GuestCartRetention::class, CustomerCartRetention::class);
    });
});
