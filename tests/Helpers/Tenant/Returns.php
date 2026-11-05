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
use App\Tenant\Payments\Actions\ChangeManualPaymentMethod;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\PaymentRefund;
use App\Tenant\Returns\Actions\ApproveReturn;
use App\Tenant\Returns\Actions\InspectReturn;
use App\Tenant\Returns\Actions\ReceiveReturn;
use App\Tenant\Returns\Actions\RequestReturn;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 * Registers the fake store-credit refund destination (module "wallet") and puts the module in the state given for the test store: "enabled", "locked" (removed by a downgrade), "disabled" (switched off by the merchant), "suspended" (the store suspended) or "unavailable". The store keeps no storage limit.
 */
function useStoreCreditDestination(string $state = 'enabled', bool $register = true): void
{
    if ($register) {
        $features = app(App\Shared\Features\FeatureRegistry::class);

        if (! $features->has(Tests\Fixtures\Payments\FakeStoreCreditDestination::FEATURE)) {
            $features->register(new App\Shared\Features\FeatureDefinition(Tests\Fixtures\Payments\FakeStoreCreditDestination::FEATURE, App\Shared\Features\FeatureKind::Module));
        }

        Tests\Fixtures\Payments\FakeStoreCreditDestination::reset();
        app(App\Tenant\Payments\RefundDestinations::class)->add(Tests\Fixtures\Payments\FakeStoreCreditDestination::class, Tests\Fixtures\Payments\FakeStoreCreditDestination::FEATURE);
    }

    putStoreCreditDestinationIn($state);
}

/**
 * Puts the fake store-credit destination's module ("wallet") in the state given for the test store, as useStoreCreditDestination() names them.
 */
function putStoreCreditDestinationIn(string $state): void
{
    $module = Tests\Fixtures\Payments\FakeStoreCreditDestination::FEATURE;
    $limits = ['storage_in_megabytes' => null];
    $storeId = test()->store->getTenantKey();
    // The fake gateways stay on the plan when a test uses them (useFakeGateway()).
    $registry = app(App\Shared\Features\FeatureRegistry::class);
    $gateways = array_values(array_filter([Tests\Fixtures\Payments\FakePaymentGateway::KEY, Tests\Fixtures\Payments\LocalFakePaymentGateway::KEY], $registry->has(...)));
    $source = app(App\Shared\Features\Contracts\FeatureSource::class);

    if (! $source instanceof FakeFeatureSource) {
        throw new LogicException('The refund destination tests need the fake feature source.');
    }

    $source->give($storeId, match ($state) {
        'enabled' => FakeFeatureSource::snapshot(entitled: [...$gateways, $module], limits: $limits),
        'locked' => FakeFeatureSource::snapshot(entitled: $gateways, removed: [$module], limits: $limits),
        'disabled' => FakeFeatureSource::snapshot(entitled: [...$gateways, $module], merchantDisabled: [$module], limits: $limits),
        'suspended' => FakeFeatureSource::snapshot(entitled: [...$gateways, $module], limits: $limits, isStoreSuspended: true),
        'unavailable' => FakeFeatureSource::snapshot(entitled: $gateways, limits: $limits),
    });
    app(App\Shared\Features\Features::class)->forget($storeId);
}

/**
 * The customer choosing, through the API, where their return's refund goes.
 */
function chooseRefundDestinationAsCustomer(OrderReturn $return, Customer $customer, string $destination): TestResponse
{
    return customerReturnRequest('PUT', "returns/{$return->public_id}/refund-destination", $customer, ['destination' => $destination]);
}

/**
 * Staff refunding units of the test store's order straight through Payments, to the destination given (the fake store credit unless said otherwise), as Returns does for a customer's choice. Returns the refund freshly read with its parts.
 *
 * @param  array<string, int>  $units  Units by order line public ID.
 */
function refundToDestination(Order $order, array $units, string $destination = Tests\Fixtures\Payments\FakeStoreCreditDestination::KEY): App\Tenant\Payments\Models\Refund
{
    return test()->store->run(static fn (): App\Tenant\Payments\Models\Refund => app(App\Tenant\Payments\Actions\RefundOrder::class)
        ->handle(StaffMember::query()->firstOrFail(), $order, new App\Tenant\Payments\Data\RefundRequest(lines: $units, reason: 'Returned', destination: $destination))
        ->refresh()
        ->load('paymentRefunds'));
}

/**
 * A paid order in the test store, by the customer given or else a guest, whose lines each shipped in one shipment: delivered (at the time given, or now), or only shipped when $deliver is false. Paid through the fake gateway (connected first, in test mode for a test order) when asked, or by a bank transfer staff recorded as received, so it can be refunded; else only marked paid. Returns it freshly read with its lines, by position.
 *
 * A bank transfer is refunded by hand, at once, without a gateway: what a
 * request handled in a process of its own (requestsAtOnce()) can refund.
 *
 * @param  list<array{0: int, 1: int}>  $lines  Each line's unit price and quantity.
 */
function returnableOrder(array $lines = [[10000, 2]], ?Customer $customer = null, bool $deliver = true, ?CarbonImmutable $deliveredAt = null, bool $isTest = false, bool $paidByGateway = false, bool $paidByHand = false): Order
{
    return test()->store->run(static function () use ($lines, $customer, $deliver, $deliveredAt, $isTest, $paidByGateway, $paidByHand): Order {
        $variants = array_map(static fn (array $line): array => [cartVariant($line[0], stock: 20), $line[1]], $lines);
        $order = placeOrder(orderDraft($variants, orderShippingMethod(), buyer: $customer === null ? null : OrderBuyer::customer($customer), paymentDueAt: $paidByHand ? CarbonImmutable::now()->addDay() : null, isTest: $isTest));
        $owner = StaffMember::query()->firstOrFail();

        if ($paidByGateway) {
            $payment = startFakePayment($order);
            gatewayAnswers($payment, GatewayPaymentState::Succeeded, $order->total, $isTest ? GatewayMode::Test : GatewayMode::Live);
            verifyFakePayment($payment);
            $order = $order->refresh()->load('lines');
        } elseif ($paidByHand) {
            app(ChangeManualPaymentMethod::class)->handle($owner, PaymentMethod::BankTransfer, ['enabled' => true]);
            app(RecordManualPayment::class)->handle($owner, app(StartManualPayment::class)->handle($order, PaymentMethod::BankTransfer), $order->total);
            $order = $order->refresh()->load('lines');
        } else {
            $order = markPaid($order)->load('lines');
        }
        $staff = AccountReference::to($owner);
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
 * A return as receivedReturn() makes it, then inspected at the store's default location with every unit back on the shelf, ready to refund.
 *
 * @param  array<string, int>|null  $units
 */
function inspectedReturn(Order $order, ?array $units = null, ReturnReason $reason = ReturnReason::ChangedMind): OrderReturn
{
    $return = receivedReturn($order, $units, $reason);

    return test()->store->run(static function () use ($return): OrderReturn {
        $outcomes = $return->lines()->with('orderLine')->get()->mapWithKeys(static fn ($line): array => [$line->orderLine->public_id => ['restocked' => $line->quantity, 'written_off' => 0]])->all();

        return app(InspectReturn::class)->handle($return, StockLocation::query()->where('is_default', true)->sole(), $outcomes, AccountReference::to(StaffMember::query()->firstOrFail()));
    });
}

/**
 * Staff refunding a return through the API, as the staff member given, with the Idempotency-Key given, or a new one each time.
 *
 * @param  array<string, mixed>  $data
 */
function refundReturnAs(OrderReturn $return, StaffMember $as, array $data = [], ?string $idempotencyKey = null): TestResponse
{
    return returnsRequest('POST', "{$return->public_id}/refund", $data, as: $as, headers: ['Idempotency-Key' => $idempotencyKey ?? 'return-refund-'.Str::lower(Str::random(16))]);
}

/**
 * Staff refunding a return, as a request for requestsAtOnce(), with the staff access token given and a new Idempotency-Key.
 *
 * @return array{url: string, headers: array<string, string>, body: array<string, mixed>}
 */
function returnRefundRequest(OrderReturn $return, string $token, string $subdomain = 'first-store'): array
{
    return [
        'url' => storeUrl($subdomain, "/api/v1/staff/returns/{$return->public_id}/refund"),
        'headers' => ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'return-refund-'.Str::lower(Str::random(16))],
        'body' => [],
    ];
}

/**
 * Staff refunding an order directly through Payments, as a request for requestsAtOnce(), with the staff access token given and a new Idempotency-Key.
 *
 * @param  array<string, mixed>  $body
 * @return array{url: string, headers: array<string, string>, body: array<string, mixed>}
 */
function orderRefundRequest(Order $order, string $token, array $body, string $subdomain = 'first-store'): array
{
    return [
        'url' => storeUrl($subdomain, "/api/v1/staff/orders/{$order->public_id}/refunds"),
        'headers' => ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => 'order-refund-'.Str::lower(Str::random(16))],
        'body' => $body,
    ];
}

/**
 * The only refund part in the test store, freshly read.
 */
function soleRefundPart(): PaymentRefund
{
    return test()->store->run(static fn (): PaymentRefund => PaymentRefund::query()->sole());
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
 * A customer's return of their order's first line, damaged, with a note and a photo; returns its public ID.
 */
function returnWithPhoto(Order $order, Customer $customer): string
{
    return customerReturnRequest('POST', "orders/{$order->public_id}/returns", $customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'damaged',
        'note' => 'Arrived cracked, call me on 0803 000 0000',
        'photos' => [returnPhoto()],
    ])->assertCreated()->json('data.id');
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

/*
|--------------------------------------------------------------------------
| For ReplacementOrdersTest.php
|--------------------------------------------------------------------------
*/

/**
 * A shipping method of the test store that sends anywhere in Nigeria for the flat price given, in minor units, or by the order-value brackets given, made through the staff API in a zone of its own.
 *
 * @param  list<array{from: int, to: int|null, amount: int}>|null  $rates
 * @param  list<string>  $regions  Such as "NG", or "NG-LA" for Lagos alone.
 */
function replacementShippingMethod(int $amount = 1500, ?array $rates = null, string $zoneName = 'Nigeria', array $regions = ['NG']): App\Tenant\Shipping\Models\ShippingMethod
{
    $zone = shippingZone($zoneName, $regions, subdomain: 'first-store');
    $id = shippingRequest('POST', '/methods', ['fulfilment' => 'ship', 'pricing' => $rates === null ? 'flat' : 'order_value', 'zone' => $zone, 'name' => ['en' => 'Replacement post'], 'rates' => $rates ?? [['from' => 0, 'to' => null, 'amount' => $amount]]], subdomain: 'first-store')->assertCreated()->json('data.id');

    return test()->store->run(static fn (): App\Tenant\Shipping\Models\ShippingMethod => App\Tenant\Shipping\Models\ShippingMethod::query()->where('public_id', $id)->sole());
}

/**
 * What a replacement order for the return is made from, as an exchange would ask: the variants given as [variant, quantity, at most (minor units) or null], with the returned items worth the amount given.
 *
 * @param  list<array{0: App\Tenant\Catalog\Models\ProductVariant, 1: int, 2?: int|null}>  $lines
 */
function replacementRequest(OrderReturn $return, array $lines, int $returnedValue, ?App\Tenant\Shipping\Models\ShippingMethod $method, bool $chargesShipping = true, ?Order $original = null): App\Tenant\Checkout\Data\ReplacementRequest
{
    $original ??= Order::query()->findOrFail($return->order_id);
    $currency = $original->currency;

    return new App\Tenant\Checkout\Data\ReplacementRequest(
        $original,
        $return->id,
        $return->public_id,
        $return->getMorphClass(),
        array_map(static fn (array $line): App\Tenant\Checkout\Data\ReplacementLine => new App\Tenant\Checkout\Data\ReplacementLine($line[0], $line[1], isset($line[2]) ? App\Shared\Money\Money::ofMinor($line[2], $currency) : null), $lines),
        App\Shared\Money\Money::ofMinor($returnedValue, $currency),
        $method,
        $chargesShipping,
        'Exchange allowance',
    );
}

/**
 * Quotes the replacement, then places it as staff confirmed that quote.
 */
function placeReplacement(App\Tenant\Checkout\Data\ReplacementRequest $request): App\Tenant\Checkout\Data\PlacedReplacement
{
    $quote = app(App\Tenant\Checkout\Actions\QuoteReplacementOrder::class)->handle($request);

    return app(App\Tenant\Checkout\Actions\PlaceReplacementOrder::class)->handle($request, $quote->hash);
}

/**
 * The quote's money, in minor units: what the returned items are worth, the replacement's total, the credit, what is still due, and what goes back.
 *
 * @return list<int>
 */
function replacementSettlement(App\Tenant\Checkout\Data\ReplacementQuote $quote): array
{
    return [$quote->returnedValue->minorAmount(), $quote->total->minorAmount(), $quote->credit->minorAmount(), $quote->amountDue->minorAmount(), $quote->difference->minorAmount()];
}

/*
|--------------------------------------------------------------------------
| For the exchange tests (Step 18d)
|--------------------------------------------------------------------------
*/

/**
 * An exchange of every unit of every line of the test store's order, requested by staff for the buyer, sending the variant given in place of each line (by order line public ID), or one variant for every line.
 *
 * @param  array<string, App\Tenant\Catalog\Models\ProductVariant>|App\Tenant\Catalog\Models\ProductVariant  $replacements
 */
function requestedExchange(Order $order, array|App\Tenant\Catalog\Models\ProductVariant $replacements, ReturnReason $reason = ReturnReason::ChangedMind): OrderReturn
{
    $units = $order->lines->mapWithKeys(static fn ($line): array => [$line->public_id => $line->quantity])->all();
    $variants = $replacements instanceof App\Tenant\Catalog\Models\ProductVariant ? array_map(static fn (): App\Tenant\Catalog\Models\ProductVariant => $replacements, $units) : $replacements;

    return test()->store->run(static fn (): OrderReturn => app(RequestReturn::class)->handle(
        ReturnRequester::staff(StaffMember::query()->firstOrFail()),
        $order,
        $units,
        $reason,
        resolution: App\Tenant\Returns\Enums\ReturnResolution::Exchange,
        replacements: array_map(static fn (App\Tenant\Catalog\Models\ProductVariant $variant): string => $variant->public_id, $variants),
    ));
}

/**
 * The lines of a return request, each with the variant to send in its place.
 *
 * @param  array<string, int>  $units  Units by order line public ID.
 * @param  array<string, string>  $replacements  Variant public IDs by order line public ID.
 * @return list<array{line: string, quantity: int, replacement?: string}>
 */
function exchangeLinesPayload(array $units, array $replacements): array
{
    return array_map(static fn (array $line): array => [...$line, ...(isset($replacements[$line['line']]) ? ['replacement' => $replacements[$line['line']]] : [])], returnLinesPayload($units));
}

/**
 * Another variant of the same product as the one given, at the price given (in minor units of the store's currency), with stock: such as another size.
 */
function siblingVariant(App\Tenant\Catalog\Models\ProductVariant $of, int $price, int $stock = 5): App\Tenant\Catalog\Models\ProductVariant
{
    $currency = $of->currency ?? 'NGN';
    $variant = App\Tenant\Catalog\Models\ProductVariant::factory()->create([
        'product_id' => $of->product_id, 'price' => App\Shared\Money\Money::ofMinor($price, $currency), 'currency' => $currency, 'attribute_signature' => 'size:'.Str::lower((string) Str::ulid()),
    ]);
    app(App\Tenant\Inventory\Actions\ChangeInventorySettings::class)->handle($variant, new App\Tenant\Inventory\Data\InventorySettingsData(tracksStock: true, allowsBackorder: false));
    app(App\Tenant\Inventory\Actions\ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), $stock);

    return $variant->load('product');
}

/**
 * A staff request for a return's exchange preview, with the shipping method given.
 */
function exchangePreviewAs(OrderReturn $return, StaffMember $as, ?App\Tenant\Shipping\Models\ShippingMethod $method = null): TestResponse
{
    return returnsRequest('GET', "{$return->public_id}/exchange-preview".($method === null ? '' : '?shipping_method='.$method->public_id), as: $as);
}

/**
 * Staff confirming a return's exchange through the API, with the hash given (or the preview's now) and a new Idempotency-Key each time unless one is given.
 */
function confirmExchangeAs(OrderReturn $return, StaffMember $as, ?App\Tenant\Shipping\Models\ShippingMethod $method = null, ?string $hash = null, ?string $idempotencyKey = null): TestResponse
{
    $hash ??= (string) exchangePreviewAs($return, $as, $method)->json('data.quote_hash');

    return returnsRequest('POST', "{$return->public_id}/exchange", array_filter(['quote_hash' => $hash, 'shipping_method' => $method?->public_id]), as: $as, headers: ['Idempotency-Key' => $idempotencyKey ?? (string) Str::ulid()]);
}

/**
 * An exchange of every unit of the test's order, requested by staff, approved, received and inspected with every unit back on the shelf: the fault confirmed as the reason claims, or overruled with the reason given.
 *
 * @param  object{order: Order}  $test
 */
function inspectedExchange(object $test, App\Tenant\Catalog\Models\ProductVariant $replacement, ReturnReason $reason, ?string $overruled = null): OrderReturn
{
    $return = requestedExchange($test->order, $replacement, $reason);

    return test()->store->run(static function () use ($return, $reason, $overruled): OrderReturn {
        $staff = AccountReference::to(StaffMember::query()->firstOrFail());
        $received = app(ReceiveReturn::class)->handle(app(ApproveReturn::class)->handle($return, $staff), $staff);
        $outcomes = $received->lines()->with('orderLine')->get()->mapWithKeys(static fn ($line): array => [$line->orderLine->public_id => ['restocked' => $line->quantity, 'written_off' => 0]])->all();
        $fault = $overruled === null ? null : new App\Tenant\Returns\Data\FaultConfirmation(! $reason->isStoresFault(), $overruled);

        return app(InspectReturn::class)->handle($received, StockLocation::query()->where('is_default', true)->sole(), $outcomes, $staff, $fault);
    });
}
