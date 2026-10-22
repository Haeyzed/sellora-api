<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Actions\CancelExpiredOrders;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\Payment;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.4: after the order is placed. The buyer back from a gateway, paying
 * again, and what payment methods held at placement: committed once the
 * order is paid, released once it expires or is cancelled, never committed
 * for an order that wasn't paid.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    [$this->owner, $this->location] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return [$owner, StockLocation::query()->sole()->public_id];
    });

    $nigeria = shippingZone('Nigeria', ['NG'], subdomain: 'cart-store');
    $this->standard = cartShippingMethod(['zone' => $nigeria, 'name' => ['en' => 'Standard'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 150000]]]);
    offerManualMethod(PaymentMethod::BankTransfer);
    FakeGiftCardPaymentMethod::reset();
});

afterEach(function (): void {
    deleteAllStores();
});

it('asks the gateway when the buyer comes back, and says only where the payment stands', function (): void {
    useFakeGateway();
    connectFakeGateway();
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $reference = placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated()->json('data.payment.reference');
    $payment = $this->store->run(static fn (): Payment => Payment::query()->sole());

    // Whatever the redirect claims, the gateway hasn't confirmed it: nothing moves. No sign-in needed.
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}?status=success&paid=1"))->assertOk()->assertExactJson(['data' => ['status' => 'pending']]);
    expect($this->store->run(static fn (): string => Order::query()->sole()->payment_status->value))->toBe('unpaid');

    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount);
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"))->assertOk()->assertExactJson(['data' => ['status' => 'succeeded']]);
    expect($this->store->run(static fn (): string => Order::query()->sole()->payment_status->value))->toBe('paid');

    $this->getJson(storeUrl('cart-store', '/api/v1/checkout/payments/pay_'.str_repeat('0', 26)))->assertNotFound()->assertJsonPath('code', 'payment_not_found');
    // Only a payment reference is ever looked up: anything else (here its public ID) never reaches the payments.
    $this->getJson(storeUrl('cart-store', '/api/v1/checkout/payments/'.$payment->public_id))->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('limits checks per payment, under a ceiling per IP address', function (): void {
    config(['api.rate_limits.payment_return' => 2, 'api.rate_limits.payment_return_ip_ceiling' => 3]);
    $first = 'pay_'.str_repeat('1', 26);
    $second = 'pay_'.str_repeat('2', 26);
    $from = static fn (string $reference, string $ip = '203.0.113.20') => test()->withServerVariables(['REMOTE_ADDR' => $ip])->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"));

    $from($first)->assertNotFound();
    $from($first)->assertNotFound();
    $from($first)->assertTooManyRequests();
    // Another payment from the same address, until the address's ceiling.
    $from($second)->assertNotFound();
    $from($second)->assertTooManyRequests();
    // Elsewhere, the first payment's own limit still counts.
    $from($first, '198.51.100.7')->assertTooManyRequests();
});

it('commits what a module held once the order is paid', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);
    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated();

    // Not paid yet: held, never committed.
    expect(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:5000']);

    recordReceived($this->store->run(static fn (): Payment => Payment::query()->sole()), 155000);
    $reference = $this->store->run(static fn (): string => (string) CheckoutPaymentHold::query()->where('method', FakeGiftCardPaymentMethod::KEY)->value('reference'));

    expect(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:5000', "commit:{$reference}"])
        ->and($this->store->run(static fn (): array => CheckoutPaymentHold::query()->orderBy('id')->pluck('status')->map->value->all()))->toBe(['committed', 'committed']);
});

it('pays at once for an order modules covered in full, committing their holds', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::reset(balance: 10_000_000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'payment_method' => null]);

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()
        ->assertJsonPath('data.order.payment_status', 'paid')
        ->assertJsonPath('data.payment_method', null)
        ->assertJsonPath('data.payment', null);

    expect(FakeGiftCardPaymentMethod::$steps)->toHaveCount(2)
        ->and(FakeGiftCardPaymentMethod::$steps[1])->toStartWith('commit:')
        ->and($this->store->run(static fn (): int => Payment::query()->count()))->toBe(0);
});

it('releases what a module held when the order expires unpaid, even once the module is off', function (): void {
    $registry = new FeatureRegistry;
    $registry->register(new FeatureDefinition('gift_cards', FeatureKind::Module));
    app()->instance(FeatureRegistry::class, $registry);
    $storeId = (string) $this->store->getTenantKey();
    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, FakeFeatureSource::snapshot(entitled: ['gift_cards'])));
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class, 'gift_cards');
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);
    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated();

    // The store turns the module off; the bank transfer never arrives.
    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give($storeId, FakeFeatureSource::snapshot(entitled: [])));
    $this->travel(30)->days();
    expect($this->store->run(static fn (): int => app(CancelExpiredOrders::class)->handle(10)))->toBe(1);

    expect(FakeGiftCardPaymentMethod::$steps)->toHaveCount(2)
        ->and(FakeGiftCardPaymentMethod::$steps[1])->toStartWith('release:')
        ->and($this->store->run(static fn (): array => CheckoutPaymentHold::query()->orderBy('id')->pluck('status')->map->value->all()))->toBe(['released', 'released']);
});

it('releases what a module held when the customer cancels before paying', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    [$ada, $variant] = $this->store->run(static fn (): array => [Customer::factory()->create(), cartVariant(10000)->public_id]);
    cartRequest('POST', as: $ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], as: $ada)->assertOk();
    $checkout = readyCheckout(as: $ada, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);
    placeCheckout($checkout['id'], $checkout['version'], as: $ada)->assertCreated();

    $this->store->run(static fn () => app(CancelOrder::class)->byCustomer($ada, Order::query()->sole()));

    expect(FakeGiftCardPaymentMethod::$steps)->toHaveCount(2)
        ->and(FakeGiftCardPaymentMethod::$steps[1])->toStartWith('release:');

    // Released once, however often it is asked.
    $this->store->run(static fn () => app(App\Tenant\Checkout\Services\PaymentHolds::class)->release(Order::query()->sole()));
    expect(FakeGiftCardPaymentMethod::$steps)->toHaveCount(2);
});

it('lets a customer pay again for their unpaid order, another way', function (): void {
    $gateway = useFakeGateway();
    connectFakeGateway();
    [$ada, $grace, $variant] = $this->store->run(static fn (): array => [Customer::factory()->create(), Customer::factory()->create(), cartVariant(10000)->public_id]);
    cartRequest('POST', as: $ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], as: $ada)->assertOk();
    $checkout = readyCheckout(as: $ada, changes: ['payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $gateway->rejectsPayments = true;
    $order = placeCheckout($checkout['id'], $checkout['version'], as: $ada, returnUrl: 'https://shop.example.com/thanks')->assertCreated()
        ->assertJsonPath('data.payment_error', 'payment_refused_by_gateway')
        ->json('data.order.id');
    $gateway->rejectsPayments = false;
    $payAgain = static fn (array $data, ?Customer $as = null, ?string $key = 'pay-again-0001') => test()->withHeaders($key === null ? [] : ['Idempotency-Key' => $key])->withToken(customerTokenFor($as ?? $ada, 'cart-store'))->postJson(storeUrl('cart-store', "/api/v1/customer/orders/{$order}/payments"), $data);

    $payAgain(['payment_method' => 'bank_transfer'], key: null)->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');
    forgetSignIns();
    $payAgain(['payment_method' => 'bank_transfer'], $grace)->assertNotFound();
    forgetSignIns();
    $payAgain(['payment_method' => 'gateway:'.FakePaymentGateway::KEY])->assertUnprocessable()->assertJsonPath('code', 'return_url_required');
    forgetSignIns();

    $paid = $payAgain(['payment_method' => 'gateway:'.FakePaymentGateway::KEY, 'return_url' => 'https://shop.example.com/thanks'], key: 'pay-again-0002')->assertCreated()
        ->assertJsonPath('data.method', 'gateway')
        ->assertJsonPath('data.status', 'pending');
    expect($paid->json('data.redirect_url'))->toStartWith('https://pay.fake.test/')
        ->and(app(Tests\Fixtures\Payments\FakeGatewayScript::class)->startedInTransactionLevels)->each->toBe(0);
});

it('takes cash at a pickup, and lets the order expire, stock and all, when nobody collects it', function (): void {
    offerManualMethod(PaymentMethod::CashOnDelivery);
    $pickup = cartShippingMethod(['fulfilment' => 'pickup', 'name' => ['en' => 'Collect in Lagos'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 0]],
        'pickup' => ['stock_location' => $this->location, 'country_code' => 'NG', 'state_code' => 'LA', 'address_line1' => '1 Broad Street', 'city' => 'Lagos']]);
    $variant = $this->store->run(static fn (): string => cartVariant(10000, stock: 3)->public_id);
    $token = newGuestCart();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], token: $token)->assertOk();
    $checkout = readyCheckout($token, changes: ['shipping_method' => $pickup, 'shipping_address' => null, 'billing_address' => lagosAddress(), 'payment_method' => 'cash_on_delivery']);

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()
        ->assertJsonPath('data.payment_method.pay_on_delivery', true);
    expect($this->store->run(static fn (): int => StockReservation::query()->where('holder_type', 'order')->count()))->toBe(1);

    $this->travel(30)->days();
    expect($this->store->run(static fn (): int => app(CancelExpiredOrders::class)->handle(10)))->toBe(1)
        ->and($this->store->run(static fn (): array => [Order::query()->sole()->status->value, StockReservation::query()->where('status', 'active')->count()]))->toBe(['cancelled', 0]);
});
