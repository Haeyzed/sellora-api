<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Models\Tenant;
use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\AccountReference;
use App\Shared\Auth\Models\Role;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Actions\AssignDriver;
use App\Tenant\Delivery\Enums\ProofOfDeliveryKind;
use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Delivery\Services\ProofOfDelivery;
use App\Tenant\Delivery\Services\ProofOfDeliveryImages;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CreateShipment;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Payments\Actions\FindCashToCollect;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Enums\PaymentMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;

/*
|--------------------------------------------------------------------------
| For DriverAuthTest.php
|--------------------------------------------------------------------------
*/

function signInAsDriver(string $phone, string $pin): TestResponse
{
    $response = test()->postJson(storeUrl('first-store', '/api/v1/driver/auth/tokens'), ['phone' => $phone, 'pin' => $pin]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/*
|--------------------------------------------------------------------------
| For DriverManagementTest.php and DriverPinTest.php
|--------------------------------------------------------------------------
*/

const DELIVERY_STAFF_PASSWORD = 'a-long-delivery-password';

function allowDrivers(string $tenantId, int $limit): void
{
    test()->featureSource->give($tenantId, FakeFeatureSource::snapshot(limits: ['drivers' => $limit]));
    app(App\Shared\Features\Features::class)->forget($tenantId);
}

/**
 * A staff member of the given store holding a role with exactly these permissions.
 *
 * @param  list<string>  $permissions
 */
function deliveryStaffWith(Tenant $store, array $permissions): StaffMember
{
    return $store->run(static function () use ($permissions): StaffMember {
        $role = Role::findOrCreate('Delivery '.implode(' ', $permissions ?: ['none']), StaffMember::GUARD);
        $role->syncPermissions(array_map(static fn (string $permission) => Permission::findOrCreate($permission, StaffMember::GUARD), $permissions));
        $staffMember = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $staffMember->assignRole($role);

        return $staffMember;
    });
}

/**
 * Sends a request to a store's staff delivery API as the given staff member, or with no token, with any headers given.
 *
 * @param  array<string, string>  $headers
 */
function deliveryRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'first-store', array $headers = []): TestResponse
{
    $request = $as === null ? test()->withoutToken() : test()->withToken(staffTokenFor($as, $subdomain));

    forgetSignIns();
    $response = $request->withHeaders($headers)->json($method, storeUrl($subdomain, '/api/v1/staff/delivery/'.$path), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Sends a request to the driver app's API with a bearer token, or with none, with any headers given.
 *
 * @param  array<string, string>  $headers
 */
function driverAppRequest(string $method, string $path, array $data = [], ?string $token = null, string $subdomain = 'first-store', array $headers = []): TestResponse
{
    $request = $token === null ? test()->withoutToken() : test()->withToken($token);

    forgetSignIns();
    $response = $request->withHeaders($headers)->json($method, storeUrl($subdomain, '/api/v1/driver/'.$path), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

function driverTokenFor(Driver $driver, Tenant $store): string
{
    return $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($driver, Driver::GUARD, 'test')->plainTextToken);
}

/**
 * Adds a driver through the staff API and returns the driver's public ID and the setup code shown once.
 *
 * @return array{id: string, code: string}
 */
function addDriverWithCode(StaffMember $as, string $phone = '+2348012345678', string $name = 'Dayo Driver'): array
{
    $response = deliveryRequest('POST', 'drivers', ['name' => $name, 'phone' => $phone], $as)->assertCreated();

    return ['id' => (string) $response->json('data.driver.id'), 'code' => (string) $response->json('data.setup_code')];
}

function chooseDriverPin(string $phone, string $code, string $pin, string $subdomain = 'first-store'): TestResponse
{
    return driverAppRequest('POST', 'auth/pin', ['phone' => $phone, 'setup_code' => $code, 'pin' => $pin], subdomain: $subdomain);
}

/**
 * Whether a driver token still works on the driver's own profile.
 */
function driverTokenWorks(string $token): bool
{
    return driverAppRequest('GET', 'auth/me', token: $token)->status() === 200;
}

/*
|--------------------------------------------------------------------------
| For DeliveriesTest.php and DriverDeliveriesTest.php
|--------------------------------------------------------------------------
*/

/**
 * An active driver of the current store, with a PIN.
 */
function activeDriver(string $phone = '+2348012345678', string $name = 'Dayo Driver'): Driver
{
    return Driver::factory()->create(['name' => $name, 'phone' => $phone, 'pin' => '482915', 'is_active' => true]);
}

/**
 * A shipment of an own-delivery order (2 of one variant, 5 in stock), waiting to leave, made in the current store by the given staff member.
 */
function pendingOwnDelivery(StaffMember $staff, bool $payOnDelivery = false): Shipment
{
    $order = ownDeliveryOrder($payOnDelivery);

    return app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 2], AccountReference::to($staff));
}

/**
 * A store for the delivery tests, in Nigeria, with room for drivers and no storage limit (proof of delivery counts towards it), its owner, and an active driver with a token.
 *
 * @return array{store: Tenant, owner: StaffMember, driver: Driver, token: string}
 */
function deliveryStore(string $subdomain = 'first-store'): array
{
    $store = createStore($subdomain, ['country_code' => 'NG']);
    test()->featureSource->give($store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['drivers' => 10, 'storage_in_megabytes' => null]));
    app(App\Shared\Features\Features::class)->forget($store->getTenantKey());
    [$owner, $driver] = $store->run(static function (): array {
        $owner = StaffMember::factory()->create(['password' => DELIVERY_STAFF_PASSWORD]);
        $owner->assignRole(StaffRole::Owner->value);

        return [$owner, activeDriver()];
    });

    return ['store' => $store, 'owner' => $owner, 'driver' => $driver, 'token' => driverTokenFor($driver, $store)];
}

/**
 * A delivery of a new own-delivery shipment, assigned to the driver by the staff member, in the given store.
 */
function assignedDelivery(Tenant $store, StaffMember $staff, Driver $driver, bool $payOnDelivery = false): Delivery
{
    return $store->run(static fn (): Delivery => app(AssignDriver::class)->handle($staff, pendingOwnDelivery($staff, $payOnDelivery), $driver));
}

/*
|--------------------------------------------------------------------------
| For DriverCashTest.php
|--------------------------------------------------------------------------
*/

/**
 * A delivery of an own-delivery order paid in cash on delivery, in the store's currency or the one given, with its cash payment pending, assigned to the driver by the staff member. Turns cash on delivery on first.
 */
function cashDelivery(Tenant $store, StaffMember $staff, Driver $driver, ?string $currency = null): Delivery
{
    offerManualMethod(PaymentMethod::CashOnDelivery);

    return $store->run(static function () use ($staff, $driver, $currency): Delivery {
        if ($currency !== null && ! DB::table('store_currencies')->where('currency_code', $currency)->exists()) {
            offerStoreCurrency($currency, '0.001');
        }

        $order = ownDeliveryOrder(payOnDelivery: true, currency: $currency);
        app(StartManualPayment::class)->handle($order, PaymentMethod::CashOnDelivery);
        $shipment = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 2], AccountReference::to($staff));

        return app(AssignDriver::class)->handle($staff, $shipment, $driver);
    });
}

/**
 * How much cash the driver should collect for the delivery, in minor units.
 */
function cashToCollect(Tenant $store, Delivery $delivery): int
{
    return $store->run(static fn (): int => app(FindCashToCollect::class)->handle($delivery->shipment->order)?->minorAmount() ?? 0);
}

/**
 * The driver picking the delivery up and handing it over, saying how much cash they collected, with a new Idempotency-Key unless one is given.
 */
function deliverWithCash(string $delivery, ?int $cashCollected, string $token, ?string $key = null): TestResponse
{
    driverAppRequest('POST', "deliveries/{$delivery}/pick-up", token: $token);

    return driverAppRequest('POST', "deliveries/{$delivery}/delivered", array_filter(['recipient_name' => 'Ada Obi', 'photo' => proofPhoto(), 'cash_collected' => $cashCollected], static fn (mixed $value): bool => $value !== null), $token, headers: ['Idempotency-Key' => $key ?? 'delivered-'.Str::lower((string) Str::ulid())]);
}

/*
|--------------------------------------------------------------------------
| For ProofOfDeliveryTest.php, and every test that delivers
|--------------------------------------------------------------------------
*/

/**
 * A small photo of the goods handed over, as the driver app uploads it.
 */
function proofPhoto(string $name = 'proof.jpg', int $width = 40, int $height = 30): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height);
}

/**
 * Proof of delivery ready for MoveDelivery, as a request hands it over: a photo, re-encoded.
 */
function proofOfDelivery(): ProofOfDelivery
{
    return new ProofOfDelivery([app(ProofOfDeliveryImages::class)->encode(ProofOfDeliveryKind::Photo, proofPhoto()) ?? throw new RuntimeException('The test photo could not be encoded.')]);
}

/**
 * The driver handing over the test's picked-up delivery, with the recipient's name and whatever proof is given.
 *
 * @param  array<string, mixed>  $data
 */
function handOver(array $data, ?string $key = null): TestResponse
{
    return driverAppRequest('POST', 'deliveries/'.test()->delivery.'/delivered', ['recipient_name' => 'Ada Obi', ...$data], test()->token, headers: ['Idempotency-Key' => $key ?? 'delivered-'.Str::lower((string) Str::ulid())]);
}

/**
 * Gives the test's store room for drivers and this much storage (null for no limit).
 */
function storageAllows(?int $megabytes): void
{
    test()->featureSource->give(test()->store->getTenantKey(), FakeFeatureSource::snapshot(limits: ['drivers' => 10, 'storage_in_megabytes' => $megabytes]));
    app(App\Shared\Features\Features::class)->forget(test()->store->getTenantKey());
}

/**
 * Records a store file of almost a megabyte, on the given connection, so a 1 MB plan is nearly full.
 */
function nearlyFullStorage(?string $connection = null): void
{
    DB::connection($connection)->table('media')->insert([
        'model_type' => 'product', 'model_id' => 1, 'uuid' => (string) Str::uuid(), 'collection_name' => 'gallery',
        'name' => 'big', 'file_name' => 'big.jpg', 'mime_type' => 'image/jpeg', 'disk' => 'public', 'size' => 1024 * 1024 - 100,
        'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]',
    ]);
}

/**
 * The media files on the test store's private disk (each in its media ID's folder), leaving out its other files such as invoice PDFs.
 *
 * @return list<string>
 */
function privateStoreFiles(): array
{
    $files = test()->store->run(static fn (): array => Illuminate\Support\Facades\Storage::disk('local')->allFiles());

    return array_values(array_filter($files, static fn (string $path): bool => preg_match('#^\d+/#', $path) === 1));
}

/*
|--------------------------------------------------------------------------
| For DriverLocationTest.php
|--------------------------------------------------------------------------
*/

/**
 * A delivery of a paid own-delivery order placed by the signed-in customer, assigned to the driver by the staff member, in the given store.
 */
function customerOwnDelivery(Tenant $store, StaffMember $staff, Driver $driver, Customer $customer): Delivery
{
    return $store->run(static function () use ($staff, $driver, $customer): Delivery {
        $draft = orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(App\Tenant\Shipping\Enums\ShippingFulfilment::OwnDelivery, 'Same day'), buyer: App\Tenant\Orders\Data\OrderBuyer::customer($customer));
        $order = markPaid(placeOrder($draft))->load('lines');
        $shipment = app(CreateShipment::class)->handle($order, [$order->lines[0]->public_id => 1], AccountReference::to($staff));

        return app(AssignDriver::class)->handle($staff, $shipment, $driver);
    });
}

/**
 * The driver's app sending a position for the delivery, with the driver's token or none.
 */
function sendLocation(string $delivery, ?string $token, float $latitude = 6.4541, float $longitude = 3.3947, ?int $accuracy = 12): TestResponse
{
    return driverAppRequest('POST', "deliveries/{$delivery}/locations", array_filter(['latitude' => $latitude, 'longitude' => $longitude, 'accuracy_meters' => $accuracy], static fn (mixed $value): bool => $value !== null), $token);
}

/*
|--------------------------------------------------------------------------
| For DeliveryPrivacyTest.php
|--------------------------------------------------------------------------
*/

/**
 * The driver reporting the test's delivery failed, with a note that names someone near the buyer.
 */
function failWithNote(): void
{
    driverAppRequest('POST', 'deliveries/'.test()->delivery.'/failed', ['reason' => 'other', 'note' => 'Gate locked, neighbour Mrs Bello said call later'], test()->token)->assertOk();
}

/**
 * A JPEG whose EXIF says the camera was turned (orientation 6: shown turned a quarter clockwise), with a comment standing in for other metadata such as a GPS position.
 *
 * Built by hand: GD writes no EXIF, so the segments are spliced in after the JPEG's start marker.
 */
function jpegWithMetadata(string $comment, int $width = 60, int $height = 20): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    // A little-endian TIFF header and one IFD entry: tag 0x0112 (Orientation), type SHORT, count 1, value 6.
    $tiff = "II\x2A\x00\x08\x00\x00\x00"."\x01\x00"."\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00"."\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $com = "\xFF\xFE".pack('n', strlen($comment) + 2).$comment;

    $path = (string) tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($path, substr($jpeg, 0, 2).$app1.$com.substr($jpeg, 2));

    return new UploadedFile($path, 'turned.jpg', 'image/jpeg', null, true);
}

/**
 * The amounts and currencies of a cash balance response, without their formatting.
 *
 * @return list<array{amount: int, currency: string}>
 */
function heldCash(TestResponse $response): array
{
    return array_map(static fn (array $balance): array => ['amount' => $balance['amount'], 'currency' => $balance['currency']], $response->json('data.balances'));
}
