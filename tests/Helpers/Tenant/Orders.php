<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Actions\IssueOrderAccessToken;
use App\Tenant\Orders\Actions\MarkOrderAsPaid;
use App\Tenant\Orders\Actions\PlaceOrder;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Data\OrderDraft;
use App\Tenant\Orders\Enums\OrderPermission;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Actions\ReservePromotionUses;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use App\Tenant\Shipping\Enums\ShippingPricing;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingZone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Orders
|--------------------------------------------------------------------------
*/

/**
 * Writes an order's rows straight into the current store's database, with one line, its discount and tax, a shipping line and a shipping address, and returns the order's ID. For testing what the database allows; real orders are placed with PlaceOrder.
 *
 * @param  array<string, mixed>  $order  Columns to change on the order.
 */
function rawOrder(array $order = []): int
{
    $variant = cartVariant(5000);
    $zone = ShippingZone::query()->firstOrCreate(['name' => 'Everywhere'], ['is_active' => true, 'covers_rest_of_world' => false]);
    $method = ShippingMethod::query()->first() ?? tap((new ShippingMethod)->forceFill([
        'shipping_zone_id' => $zone->id, 'name' => ['en' => 'Standard'], 'fulfilment' => ShippingFulfilment::Ship, 'pricing' => ShippingPricing::Flat, 'currency' => 'NGN',
    ]))->save();
    $now = now();

    $orderId = DB::table('orders')->insertGetId([
        'public_id' => strtolower((string) Str::ulid()), 'checkout_id' => strtolower((string) Str::ulid()), 'fulfilment_status' => 'unfulfilled',
        'contact_email' => 'ada@example.com', 'contact_name' => 'Ada Obi', 'contact_phone' => '+2348012345678', 'locale' => 'en',
        'currency' => 'NGN', 'base_currency' => 'NGN', 'exchange_rate' => '1', 'rate_source' => 'base', 'tax_mode' => 'inclusive',
        'tax_calculated_by' => 'rate_table', 'tax_country_code' => 'NG', 'subtotal_amount' => 10000, 'discount_amount' => 1000,
        'shipping_amount' => 1500, 'shipping_discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 10500, 'base_total_amount' => 10500,
        'customer_note' => 'Leave it at the gate.', 'placed_at' => $now, 'payment_due_at' => $now->copy()->addHour(), 'created_at' => $now, 'updated_at' => $now,
        ...$order,
    ]);
    $lineId = DB::table('order_lines')->insertGetId([
        'public_id' => strtolower((string) Str::ulid()), 'order_id' => $orderId, 'position' => 0, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
        'product_name' => 'Linen shirt', 'sku' => $variant->sku, 'requires_shipping' => true, 'weight_grams' => 500, 'tax_class_name' => 'Standard', 'tax_outcome' => 'no_rate',
        'quantity' => 2, 'currency' => 'NGN', 'unit_price_amount' => 5000, 'price_is_fixed' => false, 'subtotal_amount' => 10000, 'discount_amount' => 1000,
        'net_amount' => 9000, 'tax_amount' => 0, 'total_amount' => 9000, 'created_at' => $now,
    ]);
    DB::table('order_discounts')->insert(['order_id' => $orderId, 'order_line_id' => $lineId, 'source' => 'promotion', 'reference' => 'promo', 'label' => '10% off', 'class' => 'order', 'currency' => 'NGN', 'discount_amount' => 1000, 'created_at' => $now]);
    DB::table('order_taxes')->insert(['order_id' => $orderId, 'order_line_id' => $lineId, 'name' => 'VAT', 'country_code' => 'NG', 'rate_percent' => '0', 'currency' => 'NGN', 'tax_amount' => 0, 'created_at' => $now]);
    DB::table('order_shipping')->insert([
        'order_id' => $orderId, 'shipping_method_id' => $method->id, 'method_name' => 'Standard', 'fulfilment' => 'ship', 'currency' => 'NGN',
        'price_amount' => 1500, 'discount_amount' => 0, 'net_amount' => 1500, 'tax_amount' => 0, 'total_amount' => 1500, 'created_at' => $now,
    ]);
    DB::table('order_addresses')->insert([
        'order_id' => $orderId, 'kind' => 'shipping', 'version' => 1, 'recipient_name' => 'Ada Obi', 'line1' => '5 Allen Avenue', 'city' => 'Ikeja', 'state_code' => 'LA',
        'country_code' => 'NG', 'phone' => '+2348012345678', 'created_at' => $now,
    ]);
    DB::table('order_events')->insert(['order_id' => $orderId, 'type' => 'placed', 'created_at' => $now]);

    return $orderId;
}

/**
 * A shipping method for orders tests, made straight in the current store: shipped within a zone covering everywhere, or a pickup in Lagos.
 */
function orderShippingMethod(ShippingFulfilment $fulfilment = ShippingFulfilment::Ship, string $name = 'Standard'): ShippingMethod
{
    $zone = ShippingZone::query()->firstOrCreate(['name' => 'Everywhere'], ['is_active' => true, 'covers_rest_of_world' => false]);
    $pickup = $fulfilment === ShippingFulfilment::Pickup;
    $method = (new ShippingMethod)->forceFill([
        'shipping_zone_id' => $pickup ? null : $zone->id, 'name' => ['en' => $name, 'fr' => "{$name} (fr)"], 'fulfilment' => $fulfilment, 'pricing' => ShippingPricing::Flat,
        'currency' => app(FindStoreSettings::class)->handle()->currency_code,
        ...($pickup ? ['stock_location_id' => StockLocation::query()->sole()->id, 'pickup_country_code' => 'NG', 'pickup_state_code' => 'LA', 'pickup_address_line1' => '1 Allen Avenue', 'pickup_city' => 'Ikeja'] : []),
    ]);
    $method->save();

    return $method;
}

/**
 * A shopper's address in Lagos, as Checkout would copy it.
 */
function orderAddress(string $line1 = '5 Allen Avenue'): AddressSnapshot
{
    return new AddressSnapshot('Ada Obi', null, $line1, null, 'Ikeja', 'LA', '100271', 'NG', '+2348012345678');
}

/**
 * What Checkout would hand over for the lines given: priced with their discounts (and shipping, when a method is given), with the checkout's stock hold and promotion uses reserved, as Step 16 will.
 *
 * @param  list<array{0: ProductVariant, 1: int}>  $lines  Each variant and how many.
 */
function orderDraft(
    array $lines,
    ?ShippingMethod $method = null,
    int $shippingAmount = 1500,
    ?OrderBuyer $buyer = null,
    ?string $currency = null,
    ?string $code = null,
    ?TaxAddress $destination = null,
    ?CarbonImmutable $paymentDueAt = null,
    string $locale = 'en',
    ?string $checkout = null,
    ?AddressSnapshot $billingAddress = null,
    bool $isTest = false,
    bool $payOnDelivery = false,
): OrderDraft {
    $checkout ??= strtolower((string) Str::ulid());
    $buyer ??= OrderBuyer::guest('ada@example.com', 'Ada Obi', '+2348012345678');
    $discountBuyer = $buyer->customer === null ? DiscountBuyer::guest($buyer->email) : DiscountBuyer::customer($buyer->customer);
    $basket = discountedBasket($lines, $code, $discountBuyer, $method === null ? null : $shippingAmount, $currency, null, $destination, $checkout);
    $stockHolder = new StockHolder('checkout', $checkout);
    $promotionHolder = new RedemptionHolder('checkout', $checkout);

    app(ReserveStock::class)->handle($stockHolder, array_map(static fn (array $line): StockLine => new StockLine($line[0], $line[1]), $lines), CarbonImmutable::now()->addMinutes(15));
    app(ReservePromotionUses::class)->handle($promotionHolder, $basket->discounts, $discountBuyer, CarbonImmutable::now()->addMinutes(15));

    $option = $method === null ? null : new ShippingOption($method, Money::ofMinor($shippingAmount, $basket->priced->currency), true);

    return new OrderDraft(
        $checkout, $basket, $buyer, $billingAddress, $method !== null && $method->fulfilment->needsAddress() ? orderAddress() : null,
        $option, $stockHolder, $promotionHolder, $locale, 'Leave it at the gate.', $paymentDueAt, $isTest, $payOnDelivery,
    );
}

/**
 * Places the draft and returns the order, freshly read.
 */
function placeOrder(OrderDraft $draft): Order
{
    return app(PlaceOrder::class)->handle($draft)->order->refresh();
}

/**
 * A variant with options, named in English and French.
 */
function optionedVariant(int $price): ProductVariant
{
    $variant = cartVariant($price, stock: 5, name: 'Linen shirt');
    $variant->product->forceFill(['name' => ['en' => 'Linen shirt', 'fr' => 'Chemise en lin']])->save();
    $variant->forceFill(['sku' => 'LIN-BLU-M'])->save();

    foreach ([['Colour', 'Blue', 'Bleu'], ['Size', 'M', 'M']] as [$name, $en, $fr]) {
        $attribute = Attribute::query()->create(['name' => ['en' => $name]]);
        $value = (new AttributeValue)->forceFill(['attribute_id' => $attribute->id, 'label' => ['en' => $en, 'fr' => $fr], 'position' => 0]);
        $value->save();
        $variant->attributeValues()->attach($value->id, ['attribute_id' => $attribute->id]);
    }

    return $variant;
}

/**
 * Records the order as paid, as Payments will once a payment is confirmed (Step 15), and returns it freshly read.
 */
function markPaid(Order $order): Order
{
    return app(MarkOrderAsPaid::class)->handle($order)->refresh();
}

/**
 * Sends a request to a store's staff API, under /api/v1/staff, as the test's owner unless another staff member is given.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function staffOrderRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, array $headers = [], string $subdomain = 'orders-store'): TestResponse
{
    forgetSignIns();
    $token = test()->store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->withHeaders($headers)->json($method, storeUrl($subdomain, '/api/v1/staff'.$path), $data);
    test()->flushHeaders();
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Sends a request to a store's guest order API, under /api/v1/guest/orders, with an order access token in the X-Order-Token header when one is given.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function guestOrderRequest(string $method, string $path, ?string $token, array $data = [], array $headers = [], string $ip = '203.0.113.10', string $subdomain = 'orders-store'): TestResponse
{
    forgetSignIns();
    test()->flushHeaders();
    $request = test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders($headers);

    if ($token !== null) {
        $request = $request->withHeader(OrderAccessToken::HEADER, $token);
    }

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/guest/orders/'.$path), $data);
    test()->flushHeaders();
    test()->withServerVariables([]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Gives a guest's order in the test store its access token, as placing it at checkout does, and returns the token.
 */
function guestOrderToken(Order $order): string
{
    return test()->store->run(static fn (): string => app(IssueOrderAccessToken::class)->handle($order));
}

/**
 * The store's owner and a staff member who may only see orders.
 *
 * @return array{StaffMember, StaffMember}
 */
function orderStaff(): array
{
    $owner = StaffMember::factory()->create();
    $owner->assignRole(StaffRole::Owner->value);

    $role = Role::findOrCreate('Order viewer', StaffMember::GUARD);
    $role->givePermissionTo(Permission::findByName(OrderPermission::OrdersView->value, StaffMember::GUARD));
    $viewer = StaffMember::factory()->create();
    $viewer->assignRole($role);

    return [$owner, $viewer];
}

/**
 * A shipping address in Lagos as the API takes it, with any fields changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function orderAddressPayload(array $overrides = []): array
{
    return [...['recipient_name' => 'Ada Obi', 'line1' => '5 Allen Avenue', 'city' => 'Ikeja', 'state' => 'LA', 'postal_code' => '100271', 'country' => 'NG', 'phone' => '+2348012345678'], ...$overrides];
}

/**
 * The personal data an order still holds, by order number.
 *
 * @return array<int, array{email: string|null, name: string|null, note: string|null, line1: string|null, country: string|null, anonymised: bool, total: int}>
 */
function personalDataOnOrders(): array
{
    return test()->store->run(static fn (): array => Order::query()->with('addresses')->orderBy('number')->get()->mapWithKeys(static fn (Order $order): array => [$order->number => [
        'email' => $order->contact_email, 'name' => $order->contact_name, 'note' => $order->customer_note,
        'line1' => $order->addresses->first()?->line1, 'country' => $order->addresses->first()?->country_code,
        'anonymised' => $order->anonymised_at !== null, 'total' => $order->total->minorAmount(),
    ]])->all());
}
