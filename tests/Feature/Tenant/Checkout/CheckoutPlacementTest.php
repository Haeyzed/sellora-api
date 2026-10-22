<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\MarketingConsent;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderAccessToken;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Payments\FakeGatewayScript;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.3: placing the order for the quote the shopper confirmed, all or
 * nothing, in one transaction; the gateway only once it is committed.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $nigeria = shippingZone('Nigeria', ['NG'], subdomain: 'cart-store');
    $this->standard = cartShippingMethod(['zone' => $nigeria, 'name' => ['en' => 'Standard'], 'rates' => [['from' => 0, 'to' => null, 'amount' => 150000]]]);
    offerManualMethod(PaymentMethod::BankTransfer);
    FakeGiftCardPaymentMethod::reset();
});

afterEach(function (): void {
    deleteAllStores();
});

it('places a guest\'s order with everything held for it, and empties the cart', function (): void {
    $token = checkoutCart([[10000, 2]]);
    $this->store->run(static fn () => ProductVariant::query()->each(static fn (ProductVariant $variant) => app(App\Tenant\Inventory\Actions\ChangeInventorySettings::class)->handle($variant, new App\Tenant\Inventory\Data\InventorySettingsData(tracksStock: true, allowsBackorder: false))));
    $this->store->run(static fn () => app(App\Tenant\Inventory\Actions\ReceiveStock::class)->handle(ProductVariant::query()->sole(), App\Tenant\Inventory\Models\StockLocation::query()->sole(), 5));
    $checkout = readyCheckout($token, changes: ['marketing_consents' => ['email' => true], 'contact' => ['email' => 'ada@example.com', 'phone' => '+2348031234567'], 'buyer_tax_id' => ['id' => 'DE123456789', 'country' => 'DE']]);

    $placed = placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()
        ->assertJsonPath('data.checkout', $checkout['id'])
        ->assertJsonPath('data.order.status', 'open')
        ->assertJsonPath('data.order.payment_status', 'unpaid')
        ->assertJsonPath('data.order.total.amount', 170000)
        ->assertJsonPath('data.payment_method.key', 'bank_transfer')
        ->assertJsonPath('data.payment.status', 'pending')
        ->assertJsonPath('data.payment.redirect_url', null);
    $orderToken = $placed->headers->get(OrderAccessToken::HEADER);
    expect($orderToken)->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and($placed->getContent())->not->toContain((string) $orderToken);

    $this->store->run(static function () use ($checkout, $orderToken): void {
        $order = Order::query()->sole();
        expect($order->checkout_id)->toBe($checkout['id'])
            ->and($order->contact_email)->toBe('ada@example.com')
            ->and($order->buyer_tax_id)->toBe('DE123456789')
            ->and((int) StockReservation::query()->where('holder_type', 'order')->where('holder_id', $order->public_id)->sum('quantity'))->toBe(2)
            ->and(StockReservation::query()->where('holder_type', 'checkout')->count())->toBe(0)
            ->and(Checkout::query()->sole()->only(['status', 'order_id']))->toMatchArray(['order_id' => $order->id])
            ->and(Checkout::query()->sole()->status->value)->toBe('placed')
            ->and(Cart::query()->sole()->lines()->count())->toBe(0)
            ->and(CheckoutPaymentHold::query()->get(['method', 'status'])->map(static fn (CheckoutPaymentHold $hold): array => [$hold->method, $hold->status->value])->all())->toBe([['bank_transfer', 'held']])
            ->and(Payment::query()->sole()->method)->toBe(PaymentMethod::BankTransfer)
            // Only the hash is kept.
            ->and(OrderAccessToken::query()->sole()->token_hash)->toBe(OrderAccessToken::hash((string) $orderToken))
            // Only what was said yes to: email, not SMS.
            ->and(MarketingConsent::query()->get(['channel', 'contact', 'source'])->map(static fn (MarketingConsent $consent): array => [$consent->channel->value, $consent->contact, $consent->source->value])->all())->toBe([['email', 'ada@example.com', 'checkout']]);
    });
});

it('needs an Idempotency-Key, and gives the same order however often it is placed, the token only the first time', function (): void {
    $token = checkoutCart();
    $checkout = readyCheckout($token);

    placeCheckout($checkout['id'], $checkout['version'], token: $token, key: null)->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');

    $first = placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0001')->assertCreated();
    $replayed = placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0001')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    $again = placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0002')->assertOk();

    expect($first->headers->has(OrderAccessToken::HEADER))->toBeTrue()
        ->and($replayed->headers->has(OrderAccessToken::HEADER))->toBeFalse()
        ->and($again->headers->has(OrderAccessToken::HEADER))->toBeFalse()
        ->and($again->json('data.order.id'))->toBe($first->json('data.order.id'))
        ->and($this->store->run(static fn (): array => [Order::query()->count(), Payment::query()->count()]))->toBe([1, 1]);
});

it('places nothing when the quote isn\'t the one confirmed, and returns the new one', function (): void {
    $token = checkoutCart();
    $checkout = readyCheckout($token);

    // An older version.
    placeCheckout($checkout['id'], $checkout['version'] - 1, token: $token)->assertConflict()
        ->assertJsonPath('code', 'checkout_quote_changed')
        ->assertJsonPath('data.version', $checkout['version']);

    // The merchant changed the price since.
    $this->store->run(static fn () => ProductVariant::query()->sole()->forceFill(['price' => Money::ofMinor(12000, 'NGN')])->save());
    $changed = placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0002')->assertConflict()
        ->assertJsonPath('code', 'checkout_quote_changed')
        ->assertJsonPath('data.version', $checkout['version'] + 1)
        ->assertJsonPath('data.lines.0.unit_price.amount', 12000);

    // The cart changed since.
    cartRequest('POST', '/lines', ['variant' => $this->store->run(static fn (): string => cartVariant(500)->public_id), 'quantity' => 1], token: $token)->assertOk();
    placeCheckout($checkout['id'], $changed->json('data.version'), token: $token, key: 'place-order-0003')->assertConflict()->assertJsonPath('code', 'checkout_quote_changed');

    // The cart changed and changed back: nothing charged differs, but the shopper didn't confirm what they did.
    $settled = checkoutRequest('GET', token: $token)->json('data.version');
    $added = cartRequest('POST', '/lines', ['variant' => $this->store->run(static fn (): string => cartVariant(700)->public_id), 'quantity' => 1], token: $token)->assertOk();
    cartRequest('DELETE', '/lines/'.collect($added->json('data.lines'))->firstWhere('unit_price.amount', 700)['id'], token: $token, ifMatch: cartVersion($token))->assertOk();
    placeCheckout($checkout['id'], $settled, token: $token, key: 'place-order-0006')->assertConflict()->assertJsonPath('code', 'checkout_quote_changed')->assertJsonPath('data.version', $settled);

    // The quote expired, though nothing changed.
    $fresh = checkoutRequest('GET', token: $token)->json('data.version');
    $this->travel(16)->minutes();
    placeCheckout($checkout['id'], $fresh, token: $token, key: 'place-order-0004')->assertConflict()->assertJsonPath('data.version', $fresh + 1);

    expect($this->store->run(static fn (): array => [Order::query()->count(), StockReservation::query()->count(), Checkout::query()->sole()->status->value]))->toBe([0, 0, 'open']);

    placeCheckout($checkout['id'], $fresh + 1, token: $token, key: 'place-order-0005')->assertCreated();
});

it('places nothing while the quote has issues', function (): void {
    $token = checkoutCart();
    checkoutRequest('POST', token: $token)->assertCreated();
    $quote = checkoutRequest('PATCH', '', ['shipping_method' => $this->standard, 'shipping_address' => lagosAddress(), 'payment_method' => 'bank_transfer'], token: $token);

    placeCheckout($quote->json('data.id'), $quote->json('data.version'), token: $token)->assertConflict()
        ->assertJsonPath('code', 'checkout_not_ready')
        ->assertJsonPath('data.issues.0.code', 'contact_email_required');
    expect($this->store->run(static fn (): int => Order::query()->count()))->toBe(0);
});

it('finds only the shopper\'s own checkout', function (): void {
    $token = checkoutCart();
    $checkout = readyCheckout($token);

    placeCheckout($checkout['id'], $checkout['version'], token: newGuestCart())->assertNotFound()->assertJsonPath('code', 'checkout_not_found');
    // A token that opens no cart is nobody.
    placeCheckout($checkout['id'], $checkout['version'], token: str_repeat('b', 64))->assertUnauthorized()->assertJsonPath('code', 'idempotency_requester_unknown');
    expect($this->store->run(static fn (): int => Order::query()->count()))->toBe(0);
});

it('starts the gateway payment only once the order is committed', function (): void {
    useFakeGateway();
    connectFakeGateway();
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['payment_method' => 'gateway:'.FakePaymentGateway::KEY]);

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertUnprocessable()->assertJsonPath('code', 'return_url_required')->assertJsonValidationErrors('return_url');

    $placed = placeCheckout($checkout['id'], $checkout['version'], token: $token, key: 'place-order-0002', returnUrl: 'https://shop.example.com/thanks')->assertCreated()
        ->assertJsonPath('data.order.is_test', true)
        ->assertJsonPath('data.payment.status', 'pending');

    expect($placed->json('data.payment.redirect_url'))->toStartWith('https://pay.fake.test/')
        ->and(app(FakeGatewayScript::class)->startedInTransactionLevels)->toBe([0]);
});

it('keeps the order when the gateway doesn\'t answer, to be paid again', function (): void {
    $gateway = useFakeGateway();
    connectFakeGateway();
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $gateway->unavailable = true;

    placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated()
        ->assertJsonPath('data.payment', null)
        ->assertJsonPath('data.payment_error', 'payment_gateway_unreachable');
    expect($this->store->run(static fn (): int => Order::query()->count()))->toBe(1);
});

it('places a signed-in customer\'s order on their account, with no order token', function (): void {
    [$ada, $variant] = $this->store->run(static fn (): array => [Customer::factory()->create(['email' => 'ada@example.com']), cartVariant(10000)->public_id]);
    cartRequest('POST', as: $ada)->assertCreated();
    cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], as: $ada)->assertOk();
    $checkout = readyCheckout(as: $ada, changes: ['marketing_consents' => ['email' => true]]);

    $placed = placeCheckout($checkout['id'], $checkout['version'], as: $ada)->assertCreated();

    expect($placed->headers->has(OrderAccessToken::HEADER))->toBeFalse()
        ->and($this->store->run(static fn (): array => [Order::query()->sole()->customer_id, OrderAccessToken::query()->count(), MarketingConsent::query()->sole()->customer_id]))->toBe([$ada->id, 0, $ada->id]);
});

it('places nothing when a module\'s method can no longer hold what the quote showed', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);
    FakeGiftCardPaymentMethod::$holdsShortBy = 1000;

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertConflict()->assertJsonPath('code', 'checkout_quote_changed');
    expect($this->store->run(static fn (): array => [Order::query()->count(), CheckoutPaymentHold::query()->count(), StockReservation::query()->count()]))->toBe([0, 0, 0]);
});

it('keeps on the order the exchange rate its quote was shown at', function (): void {
    $token = checkoutCart();
    $this->store->run(static fn () => offerStoreCurrency('USD', '0.001'));
    cartRequest('PATCH', '', ['currency' => 'USD'], token: $token, ifMatch: cartVersion($token))->assertOk();
    $checkout = readyCheckout($token);

    // The merchant doubles the rate after the shopper saw the quote.
    $this->store->run(static fn () => DB::table('store_currencies')->where('currency_code', 'USD')->update(['manual_rate' => '0.002', 'manual_rate_set_at' => now()]));
    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()->assertJsonPath('data.order.total.currency', 'USD');

    $this->store->run(static function (): void {
        $order = Order::query()->sole();
        expect($order->exchange_rate->toString())->toBe('0.001000000000')
            ->and($order->lines()->sole()->unit_price->minorAmount())->toBe(10);
    });
});

it('has modules\' methods hold their part first, then core\'s the rest', function (): void {
    app(CheckoutExtensions::class)->addPaymentMethod(FakeGiftCardPaymentMethod::class);
    FakeGiftCardPaymentMethod::reset(balance: 5000);
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY]]);

    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()->assertJsonPath('data.payment.amount.amount', 160000 - 5000);

    expect(FakeGiftCardPaymentMethod::$steps)->toBe(['hold:5000'])
        ->and($this->store->run(static fn (): array => CheckoutPaymentHold::query()->orderBy('id')->get()->map(static fn (CheckoutPaymentHold $hold): array => [$hold->method, $hold->amount->minorAmount(), $hold->status->value, $hold->reference !== null])->all()))
        ->toBe([[FakeGiftCardPaymentMethod::KEY, 5000, 'held', true], ['bank_transfer', 0, 'held', false]]);

    // The transfer of what is left pays the order in full.
    expect($this->store->run(static fn (): string => Order::query()->sole()->payment_status->value))->toBe('unpaid');
    recordReceived($this->store->run(static fn (): Payment => Payment::query()->sole()), 155000);
    expect($this->store->run(static fn (): string => Order::query()->sole()->payment_status->value))->toBe('paid');
});
