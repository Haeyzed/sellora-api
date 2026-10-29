<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccountReference;
use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Actions\MoveShipment;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderLine;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Returns\Actions\ApproveReturn;
use App\Tenant\Returns\Actions\ReceiveReturn;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For the Returns tests
|--------------------------------------------------------------------------
*/

/**
 * A staff member of the given store holding a role with exactly these permissions.
 *
 * @param  list<string>  $permissions
 */
function returnsStaffWith(Tenant $store, array $permissions): StaffMember
{
    return $store->run(static function () use ($permissions): StaffMember {
        $role = Role::findOrCreate('Returns '.implode(' ', $permissions ?: ['none']), StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (string $permission) => Permission::findOrCreate($permission, StaffMember::GUARD), $permissions));
        $staffMember = StaffMember::factory()->create();
        $staffMember->assignRole($role);

        return $staffMember;
    });
}

/**
 * A store for the returns tests, in Nigeria, with no storage limit (return photos count towards it), and its owner. Sets test()->store.
 *
 * @return array{store: Tenant, owner: StaffMember}
 */
function returnsStore(string $subdomain = 'first-store'): array
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    test()->featureSource->give($store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['storage_in_megabytes' => null]));
    app(App\Shared\Features\Features::class)->forget($store->getTenantKey());
    test()->store = $store;
    $owner = $store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    return ['store' => $store, 'owner' => $owner];
}

/**
 * A paid order in the test store, by the customer given or else a guest, whose lines each shipped in one shipment: delivered (at the time given, or now), or only shipped when $deliver is false. Paid through the fake gateway (connected first) when asked, so it can be refunded; else only marked paid. Returns it freshly read with its lines, by position.
 *
 * @param  list<array{0: int, 1: int}>  $lines  Each line's unit price and quantity.
 */
function returnableOrder(array $lines = [[10000, 2]], ?Customer $customer = null, bool $deliver = true, ?CarbonImmutable $deliveredAt = null, bool $isTest = false, bool $paidByGateway = false): Order
{
    return test()->store->run(static function () use ($lines, $customer, $deliver, $deliveredAt, $isTest, $paidByGateway): Order {
        $variants = array_map(static fn (array $line): array => [cartVariant($line[0], stock: 20), $line[1]], $lines);
        $order = placeOrder(orderDraft($variants, orderShippingMethod(), buyer: $customer === null ? null : OrderBuyer::customer($customer), isTest: $isTest));

        if ($paidByGateway) {
            $payment = startFakePayment($order);
            gatewayAnswers($payment, GatewayPaymentState::Succeeded, $order->total, GatewayMode::Live);
            verifyFakePayment($payment);
            $order = $order->refresh()->load('lines');
        } else {
            $order = markPaid($order)->load('lines');
        }
        $staff = AccountReference::to(StaffMember::query()->firstOrFail());
        $shipment = app(CreateShipment::class)->handle($order, $order->lines->mapWithKeys(static fn ($line): array => [$line->public_id => $line->quantity])->all(), $staff, 'TRK');
        $shipment = app(MoveShipment::class)->ship($shipment, $staff);

        if ($deliver) {
            app(MoveShipment::class)->deliver($shipment, $staff);

            if ($deliveredAt !== null) {
                Shipment::query()->whereKey($shipment->id)->update(['delivered_at' => $deliveredAt]);
            }
        }

        return $order->refresh()->load(['lines' => static fn ($query) => $query->orderBy('position')]);
    });
}

/**
 * A return of the test store's order, requested by staff for the buyer: of the units given by order line public ID, or of every unit of every line.
 *
 * @param  array<string, int>|null  $units
 */
function requestedReturn(Order $order, ?array $units = null, ReturnReason $reason = ReturnReason::ChangedMind): OrderReturn
{
    $units ??= $order->lines->mapWithKeys(static fn ($line): array => [$line->public_id => $line->quantity])->all();

    return test()->store->run(static fn (): OrderReturn => app(RequestReturn::class)->handle(ReturnRequester::staff(StaffMember::query()->firstOrFail()), $order, $units, $reason));
}

/**
 * A return as requestedReturn() makes it, then approved and its items received, ready to inspect.
 *
 * @param  array<string, int>|null  $units
 */
function receivedReturn(Order $order, ?array $units = null, ReturnReason $reason = ReturnReason::ChangedMind): OrderReturn
{
    $return = requestedReturn($order, $units, $reason);

    return test()->store->run(static function () use ($return): OrderReturn {
        $staff = AccountReference::to(StaffMember::query()->firstOrFail());

        return app(ReceiveReturn::class)->handle(app(ApproveReturn::class)->handle($return, $staff), $staff);
    });
}

/**
 * On hand of an order line's variant at the test store's default location.
 */
function onHandAtDefaultLocation(OrderLine $line): int
{
    return test()->store->run(static fn (): int => StockItem::query()
        ->where('product_variant_id', $line->product_variant_id)
        ->where('stock_location_id', StockLocation::query()->where('is_default', true)->value('id'))
        ->sole()->on_hand);
}

/**
 * The return lines to send for an order's lines: units by line, in the API's shape.
 *
 * @param  array<string, int>  $units  Units by order line public ID.
 * @return list<array{line: string, quantity: int}>
 */
function returnLinesPayload(array $units): array
{
    return array_values(array_map(static fn (string $line, int $quantity): array => ['line' => $line, 'quantity' => $quantity], array_keys($units), $units));
}

/**
 * A customer of the test store asking for a return through the API.
 *
 * @param  array<string, mixed>  $data
 */
function customerReturnRequest(string $method, string $path, Customer $customer, array $data = [], string $subdomain = 'first-store'): TestResponse
{
    forgetSignIns();
    $response = test()->withToken(customerTokenFor($customer, $subdomain))->json($method, storeUrl($subdomain, '/api/v1/customer/'.ltrim($path, '/')), $data);
    test()->withoutToken();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A guest acting on their order's returns through the API, with the order's access token in the X-Order-Token header when one is given.
 *
 * @param  array<string, mixed>  $data
 */
function guestReturnRequest(string $method, string $path, ?string $token, array $data = [], string $ip = '203.0.113.10', string $subdomain = 'first-store'): TestResponse
{
    return guestOrderRequest($method, $path, $token, $data, ip: $ip, subdomain: $subdomain);
}

/**
 * Staff starting a return for an order's buyer through the API.
 *
 * @param  array<string, mixed>  $data
 */
function startReturnAsStaff(Order $order, array $data, StaffMember $as, string $subdomain = 'first-store'): TestResponse
{
    forgetSignIns();
    $response = test()->withToken(staffTokenFor($as, $subdomain))->json('POST', storeUrl($subdomain, "/api/v1/staff/orders/{$order->public_id}/returns"), $data);
    test()->withoutToken();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * A small JPEG upload for a return photo.
 */
function returnPhoto(string $name = 'damage.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 40, 30);
}

/**
 * The media files on the test store's private disk: those in folders named by a media ID, where return photos are kept.
 *
 * @return list<string>
 */
function returnPhotoFiles(): array
{
    return test()->store->run(static fn (): array => array_values(array_filter(
        Storage::disk(config()->string('media.return_photos.disk'))->allFiles(),
        static fn (string $path): bool => ctype_digit(explode('/', $path)[0]),
    )));
}

/**
 * Sends a request to a store's staff returns API as the given staff member, or with no token, with any headers given.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function returnsRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'first-store', array $headers = []): TestResponse
{
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));

    forgetSignIns();
    $response = $request->withHeaders($headers)->json($method, storeUrl($subdomain, '/api/v1/staff/returns/'.ltrim($path, '/')), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}
