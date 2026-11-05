<?php

declare(strict_types=1);

use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderDiscount;
use App\Tenant\Payments\Enums\PaymentMethod;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18c: price allowances belong to replacement orders alone. Nothing a
 * shopper sends reaches them: a field named like them on the cart, the
 * checkout or placing the order is ignored, and the order is charged in full.
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

it('never offers the exchange credit at checkout, so a shopper can\'t choose it', function (): void {
    $token = checkoutCart([[10000, 1]]);
    readyCheckout($token);

    $quote = checkoutRequest('PATCH', '', ['payment_method' => 'exchange_credit'], token: $token)->assertOk();

    expect(array_column($quote->json('data.payment_methods'), 'key'))->not->toContain('exchange_credit')
        ->and(checkoutIssues($quote))->toContain('payment_method_not_offered');
});

it('ignores allowances a shopper sends to the cart, the checkout or the order', function (): void {
    $token = checkoutCart([[10000, 1]]);
    $sent = ['allowances' => ['lines' => ['line-1' => 10000], 'shipping' => 150000], 'allowance' => 10000];

    $cart = cartRequest('PATCH', '', $sent, token: $token, ifMatch: cartVersion($token));
    expect($cart->status())->toBeIn([200, 422]);

    $checkout = readyCheckout($token, changes: $sent);
    $quote = checkoutRequest('GET', token: $token)->assertOk();

    expect($quote->json('data.totals.discount.amount'))->toBe(0)
        ->and($quote->json('data.totals.total.amount'))->toBe(10000 + 150000);

    $placed = checkoutRequest('POST', '/orders', ['checkout' => $checkout['id'], 'version' => $checkout['version'], ...$sent], token: $token, headers: ['Idempotency-Key' => 'place-order-0001'])->assertCreated();

    $order = $this->store->run(static fn (): array => [Order::query()->sole()->total->minorAmount(), OrderDiscount::query()->count()]);

    expect($placed->json('data.order.total.amount') ?? $order[0])->toBe(10000 + 150000)
        ->and($order)->toBe([10000 + 150000, 0]);
});
