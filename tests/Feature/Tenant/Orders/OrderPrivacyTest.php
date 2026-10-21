<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Retention\PurgeExpiredRecords;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderNote;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14f: orders and personal data. Orders are financial records, so
 * erasing a buyer anonymises them rather than deleting them: contact details,
 * note, addresses (but the country and state), corrections and staff notes
 * go; amounts and tax stay. An unpaid order is cancelled first; one paid for
 * and still being fulfilled waits until it settles. Every settled order is
 * anonymised once the store's retention period for orders has passed: its
 * own setting, or its country's default. Guests' requests are answered by
 * staff, by email.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
});

afterEach(function (): void {
    deleteAllStores();
});

it('anonymises a customer\'s settled orders on erasure, cancels an unpaid one first, and keeps a paid one being fulfilled until it settles', function (): void {
    $customer = firstStoreCustomer(['email_verified_at' => now()]);
    [$paidOrder] = $this->store->run(function () use ($customer): array {
        $method = orderShippingMethod();
        $buyer = OrderBuyer::customer($customer, name: 'Ada Obi');
        $staff = AccountReference::to($this->owner);

        $unpaid = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], $method, buyer: $buyer));
        $completed = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], $method, buyer: $buyer)))->load('lines');
        app(MoveShipment::class)->deliver(app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($completed, [$completed->lines[0]->public_id => 1], $staff, 'TRK'), $staff), $staff);
        $paid = markPaid(placeOrder(orderDraft([[cartVariant(10000, stock: 5), 1]], $method, buyer: $buyer)))->load('lines');
        (new OrderNote)->forceFill(['order_id' => $completed->id, 'author_staff_id' => $this->owner->id, 'body' => 'Ada asked for gift wrap.'])->save();
        DB::table('order_contact_corrections')->insert(['order_id' => $completed->id, 'email' => 'ada.work@example.com', 'name' => 'Ada', 'phone' => null, 'created_by_type' => 'staff_member', 'created_by_id' => $this->owner->public_id, 'created_at' => now()]);

        app(EraseCustomer::class)->erase($customer);

        return [$paid->public_id, $unpaid->public_id];
    });

    expect(personalDataOnOrders())->toBe([
        1001 => ['email' => null, 'name' => null, 'note' => null, 'line1' => null, 'country' => 'NG', 'anonymised' => true, 'total' => 11500],
        1002 => ['email' => null, 'name' => null, 'note' => null, 'line1' => null, 'country' => 'NG', 'anonymised' => true, 'total' => 11500],
        1003 => ['email' => 'ada@example.com', 'name' => 'Ada Obi', 'note' => 'Leave it at the gate.', 'line1' => '5 Allen Avenue', 'country' => 'NG', 'anonymised' => false, 'total' => 11500],
    ])
        ->and($this->store->run(static fn (): array => [Order::query()->where('number', 1001)->sole()->status->value, Order::query()->where('number', 1003)->sole()->anonymise_when_settled]))->toBe(['cancelled', true])
        ->and($this->store->run(static fn (): array => [OrderNote::query()->count(), DB::table('order_contact_corrections')->whereNotNull('email')->count()]))->toBe([0, 0])
        ->and($this->store->run(static fn (): array => Order::query()->where('number', 1002)->sole()->events()->pluck('type')->map->value->all()))->toContain('anonymised');

    // Once the paid order settles, the daily purge anonymises it.
    $this->store->run(function () use ($paidOrder): void {
        $staff = AccountReference::to($this->owner);
        $order = Order::query()->where('public_id', $paidOrder)->with('lines')->sole();
        app(MoveShipment::class)->deliver(app(MoveShipment::class)->ship(app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], $staff, 'TRK'), $staff), $staff);
        PurgeExpiredRecords::dispatchSync();
    });

    expect(personalDataOnOrders()[1003])->toMatchArray(['email' => null, 'line1' => null, 'anonymised' => true, 'total' => 11500]);
});

it('puts a customer\'s orders in their data export', function (): void {
    $customer = firstStoreCustomer(['email_verified_at' => now()]);
    $this->store->run(static fn () => placeOrder(orderDraft([[cartVariant(10000), 2]], orderShippingMethod(), buyer: OrderBuyer::customer($customer))));

    $export = staffCustomerRequest('GET', "/{$customer->public_id}/personal-data", as: $this->owner)->assertOk()->json('data.orders');

    expect($export)->toHaveCount(1)
        ->and($export[0])->toMatchArray(['number' => 1001, 'status' => 'open', 'note' => 'Leave it at the gate.'])
        ->and($export[0]['shipping_address']['line1'])->toBe('5 Allen Avenue')
        ->and($export[0]['lines'][0])->toMatchArray(['product' => 'Linen shirt', 'quantity' => 2]);
});

it('answers a guest\'s data request by email: their guest orders only, erased with the staff member\'s password', function (): void {
    $this->store->run(static function (): void {
        $method = orderShippingMethod();
        markPaid(placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('guest@example.com'))));
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('guest@example.com')));
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('someone@example.com')));
        $linked = Customer::factory()->create(['email' => 'member@example.com', 'email_verified_at' => now()]);
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('member@example.com')));
        expect(Order::query()->where('number', 1004)->sole()->customer_id)->toBe($linked->id);
    });

    expect(staffOrderRequest('POST', '/guest-personal-data', ['email' => 'GUEST@example.com'], subdomain: 'first-store')->assertOk()->json('data.orders.*.number'))->toBe([1001, 1002])
        ->and(staffOrderRequest('POST', '/guest-personal-data', ['email' => 'member@example.com'], subdomain: 'first-store')->json('data.orders'))->toBe([]);

    staffOrderRequest('POST', '/guest-personal-data', ['email' => 'guest@example.com'], as: $this->viewer, subdomain: 'first-store')->assertForbidden();
    staffOrderRequest('POST', '/guest-personal-data/erasure', ['email' => 'guest@example.com', 'password' => 'wrong'], subdomain: 'first-store')->assertJsonPath('code', 'current_password_incorrect');
    staffOrderRequest('POST', '/guest-personal-data/erasure', ['email' => 'guest@example.com', 'password' => 'Password@123'], subdomain: 'first-store')->assertOk();

    $data = personalDataOnOrders();
    // The paid one is still being fulfilled, so it keeps what it needs until it settles.
    expect($data[1001])->toMatchArray(['email' => 'guest@example.com', 'anonymised' => false])
        ->and($data[1002])->toMatchArray(['email' => null, 'anonymised' => true])
        ->and($data[1003]['email'])->toBe('someone@example.com')
        ->and($this->store->run(static fn (): array => [Order::query()->where('number', 1001)->sole()->anonymise_when_settled, Order::query()->where('number', 1002)->sole()->status->value, Order::query()->where('number', 1002)->sole()->cancellation_reason?->value]))->toBe([true, 'cancelled', 'data_erased'])
        ->and($this->store->run(static fn (): array => Activity::query()->inLog('orders')->pluck('description')->all()))->toBe(['Erased the personal data of a guest']);
});

it('anonymises settled orders once the store\'s retention period has passed: its own setting, or its country\'s default', function (?int $setting, string $country, int $years): void {
    $this->store->run(static function () use ($setting, $country, $years): void {
        StoreSettings::query()->whereKey(1)->update(['order_retention_years' => $setting, 'country_code' => $country]);
        $method = orderShippingMethod();
        $old = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        $recent = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, paymentDueAt: now()->addDays(5)->toImmutable()));
        // Cancelled as long ago, but its payment is still owed back: it keeps what the refund needs.
        $owing = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        DB::table('orders')->where('id', $owing->id)->update(['status' => 'cancelled', 'cancellation_reason' => 'staff', 'cancelled_at' => now()->subYears($years)->subDay(), 'payment_status' => 'paid', 'paid_at' => now()->subYears($years)->subDays(2), 'refund_due' => true]);
        DB::table('orders')->where('id', $old->id)->update(['status' => 'cancelled', 'cancellation_reason' => 'staff', 'cancelled_at' => now()->subYears($years)->subDay()]);
        DB::table('orders')->where('id', $recent->id)->update(['status' => 'cancelled', 'cancellation_reason' => 'staff', 'cancelled_at' => now()->subYears($years)->addDay()]);
        PurgeExpiredRecords::dispatchSync();
    });

    expect(array_map(static fn (array $order): bool => $order['anonymised'], personalDataOnOrders()))->toBe([1001 => true, 1002 => false, 1003 => false, 1004 => false]);
})->with([
    'Nigeria by default: 6 years' => [null, 'NG', 6],
    'Germany by default: 10 years' => [null, 'DE', 10],
    'a country not listed: 10 years' => [null, 'KE', 10],
    'the store\'s own setting' => [8, 'NG', 8],
]);

it('lets the owner set the store\'s retention period for orders within 3 to 30 years, or go back to the country\'s', function (): void {
    $settings = static fn (array $data) => test()->withToken(test()->store->run(static fn (): string => app(App\Shared\Auth\AccessTokenIssuer::class)->issue(test()->owner, StaffMember::GUARD, 'test')->plainTextToken))->patchJson(storeUrl('first-store', '/api/v1/staff/store/settings'), $data);

    $settings(['order_retention_years' => 7])->assertOk()->assertJsonPath('data.order_retention_years', 7)->assertJsonPath('data.order_retention_default_years', 6);
    $settings(['order_retention_years' => 2])->assertJsonValidationErrors('order_retention_years');
    $settings(['order_retention_years' => 31])->assertJsonValidationErrors('order_retention_years');
    $settings(['order_retention_years' => null])->assertOk()->assertJsonPath('data.order_retention_years', null);
});
