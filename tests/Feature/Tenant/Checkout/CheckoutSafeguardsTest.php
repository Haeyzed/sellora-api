<?php

declare(strict_types=1);

use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\PaymentMethods\GatewayPaymentMethods;
use App\Tenant\Checkout\PaymentMethods\ManualPaymentMethods;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\OrderAccessToken;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\Payment;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.6: what holds checkout together. Core's own payment methods are
 * wired in first; checking out is limited per shopper under a ceiling per
 * IP address, per store; nothing personal and no token is ever logged; and
 * one store's carts, checkouts, payments and orders mean nothing in another.
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
});

afterEach(function (): void {
    deleteAllStores();
});

it('wires in core\'s own payment methods first, gateways then those paid by hand, once for the whole app', function (): void {
    $extensions = app(CheckoutExtensions::class);

    expect($extensions)->toBe(app(CheckoutExtensions::class))
        ->and(array_map(get_class(...), $this->store->run(static fn (): array => $extensions->paymentMethods())))->toBe([GatewayPaymentMethods::class, ManualPaymentMethods::class])
        ->and($extensions->paymentMethodFor('gateway:'.FakePaymentGateway::KEY))->toBeInstanceOf(GatewayPaymentMethods::class)
        ->and($extensions->paymentMethodFor('bank_transfer'))->toBeInstanceOf(ManualPaymentMethods::class)
        ->and($extensions->paymentMethodFor('gift_card'))->toBeNull();

    // A class that isn't a payment method is a wiring mistake, never silently skipped.
    $wrong = new CheckoutExtensions(app());
    $wrong->addPaymentMethod(stdClass::class);
    expect(fn () => $this->store->run(static fn (): array => $wrong->paymentMethods()))->toThrow(LogicException::class);
});

it('limits checking out per cart, under a ceiling per IP address, each store counted apart', function (): void {
    config(['api.rate_limits.checkout' => 2, 'api.rate_limits.checkout_ip_ceiling' => 3]);
    $first = checkoutCart();
    $second = checkoutCart();
    $other = createStore('other-store', ['country_code' => 'NG']);
    $theirs = (string) cartRequest('POST', subdomain: 'other-store')->assertCreated()->json('data.token');
    $from = static fn (string $token, string $ip = '203.0.113.20', string $subdomain = 'cart-store') => checkoutRequest('GET', token: $token, subdomain: $subdomain, ip: $ip);

    $from($first)->assertNotFound();
    $from($first)->assertNotFound();
    $from($first)->assertTooManyRequests();
    // Another cart from the same address, until the address's ceiling.
    $from($second)->assertNotFound();
    $from($second)->assertTooManyRequests();
    // Elsewhere, the first cart's own limit still counts.
    $from($first, '198.51.100.7')->assertTooManyRequests();
    // Another store keeps its own counts, from the same address.
    $from($theirs, subdomain: 'other-store')->assertNotFound();

    expect($other->getTenantKey())->not->toBe($this->store->getTenantKey());
});

it('never logs a token, the buyer\'s contact details or their address, from the cart to paying', function (): void {
    $logged = [];
    Log::listen(static function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message->message.' '.json_encode($message->context);
    });
    useFakeGateway();
    connectFakeGateway();
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: [
        'contact' => ['email' => 'ada.private@example.com', 'name' => 'Ada Private', 'phone' => '+2348031112222'],
        'shipping_address' => lagosAddress(['line1' => '77 Hidden Close', 'phone' => '+2348031112222']),
        'buyer_tax_id' => ['id' => '98765432', 'country' => 'NG'],
        'payment_method' => 'gateway:'.FakePaymentGateway::KEY,
    ]);
    $placed = placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated();
    $orderToken = (string) $placed->headers->get(OrderAccessToken::HEADER);
    $reference = (string) $placed->json('data.payment.reference');
    $payment = $this->store->run(static fn (): Payment => Payment::query()->sole());

    // The guest cancels, then the gateway says the money went through after all: that is logged, for staff.
    guestOrderRequest('POST', $placed->json('data.order.id').'/cancel', $orderToken, subdomain: 'cart-store')->assertOk();
    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount);
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"))->assertOk()->assertJsonPath('data.status', 'succeeded');
    guestOrderRequest('GET', (string) $placed->json('data.order.id'), $orderToken, subdomain: 'cart-store')->assertOk()->assertJsonPath('data.status', 'cancelled');
    guestOrderRequest('GET', (string) $placed->json('data.order.id').'?token='.$orderToken, null, subdomain: 'cart-store')->assertNotFound();

    $secrets = [$token, $orderToken, 'ada.private@example.com', 'Ada Private', '+2348031112222', '2348031112222', '77 Hidden Close', '98765432'];
    expect($orderToken)->toHaveLength(64)
        // Logged, without who paid.
        ->and(implode("\n", $logged))->toContain('payment arrived for a cancelled order');

    foreach ($logged as $line) {
        foreach ($secrets as $secret) {
            expect($line)->not->toContain($secret);
        }
    }
});

it('gives one store\'s carts, checkouts, payments and orders no meaning in another', function (): void {
    useFakeGateway();
    connectFakeGateway();
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['payment_method' => 'gateway:'.FakePaymentGateway::KEY]);
    $placed = placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated();
    $orderId = (string) $placed->json('data.order.id');
    $orderToken = (string) $placed->headers->get(OrderAccessToken::HEADER);
    $reference = (string) $placed->json('data.payment.reference');

    createStore('other-store', ['country_code' => 'NG']);
    $theirCart = (string) cartRequest('POST', subdomain: 'other-store')->assertCreated()->json('data.token');

    // This store's cart token is unknown there: it finds no checkout, and says nothing more.
    checkoutRequest('GET', token: $token, subdomain: 'other-store')->assertNotFound()->assertJsonPath('code', 'checkout_not_found');
    // This store's checkout, from a cart of theirs.
    checkoutRequest('POST', '/orders', ['checkout' => $checkout['id'], 'version' => $checkout['version']], token: $theirCart, subdomain: 'other-store', headers: ['Idempotency-Key' => 'place-order-0001'])
        ->assertNotFound()->assertJsonPath('code', 'checkout_not_found');
    // This store's payment, and its order with its own token.
    $this->getJson(storeUrl('other-store', "/api/v1/checkout/payments/{$reference}"))->assertNotFound()->assertJsonPath('code', 'payment_not_found');
    guestOrderRequest('GET', $orderId, $orderToken, subdomain: 'other-store')->assertNotFound();
    guestOrderRequest('POST', "{$orderId}/payments", $orderToken, ['payment_method' => 'bank_transfer'], ['Idempotency-Key' => 'pay-again-0001'], subdomain: 'other-store')->assertNotFound();

    // Still all there at home.
    guestOrderRequest('GET', $orderId, $orderToken, subdomain: 'cart-store')->assertOk();
    $this->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$reference}"))->assertOk();
});
