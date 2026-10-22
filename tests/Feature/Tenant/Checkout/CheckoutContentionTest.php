<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.3: placing orders at the same moment, from separate PHP processes
 * (each with its own database connection, as separate requests would be):
 * the locks are taken in one order, so the same checkout is placed once, and
 * the last unit or the last use of a promotion goes to exactly one buyer.
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

it('places a checkout pressed twice at once only once', function (): void {
    $token = checkoutCart();
    $checkout = readyCheckout($token);

    $results = placeAtOnce([
        ['token' => $token, 'checkout' => $checkout['id'], 'version' => $checkout['version'], 'key' => 'double-press-0001'],
        ['token' => $token, 'checkout' => $checkout['id'], 'version' => $checkout['version'], 'key' => 'double-press-0002'],
    ]);

    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([200, 201])
        ->and($results[0]['body']['data']['order']['id'])->toBe($results[1]['body']['data']['order']['id'])
        ->and($this->store->run(static fn (): int => Order::query()->count()))->toBe(1);
});

it('gives the last unit to exactly one of two buyers', function (): void {
    $variant = $this->store->run(static fn (): string => cartVariant(10000, stock: 1)->public_id);
    $checkouts = [];

    foreach (['ada@example.com', 'grace@example.com'] as $email) {
        $token = newGuestCart();
        cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], token: $token)->assertOk();
        $checkouts[] = ['token' => $token, ...readyCheckout($token, changes: ['contact' => ['email' => $email]])];
    }

    $results = placeAtOnce(array_map(static fn (array $checkout, int $index): array => ['token' => $checkout['token'], 'checkout' => $checkout['id'], 'version' => $checkout['version'], 'key' => "last-item-000{$index}"], $checkouts, array_keys($checkouts)));

    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([201, 409]);

    $refused = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 409))[0]['body'];
    expect($refused['code'])->toBe('checkout_quote_changed')
        ->and($refused['data']['lines'][0]['status'])->toBe('out_of_stock')
        ->and($this->store->run(static fn (): array => [Order::query()->count(), (int) StockReservation::query()->where('product_variant_id', ProductVariant::query()->where('public_id', $variant)->value('id'))->sum('quantity')]))->toBe([1, 1]);
});

it('gives the last use of a promotion to exactly one of two buyers', function (): void {
    $this->store->run(static fn () => runningPromotion(['percent' => Decimal::of(10), 'usage_limit' => 1]));
    $checkouts = [];

    foreach (['ada@example.com', 'grace@example.com'] as $email) {
        $token = checkoutCart();
        $checkouts[] = ['token' => $token, ...readyCheckout($token, changes: ['contact' => ['email' => $email]])];
        expect(checkoutRequest('GET', token: $token)->json('data.totals.discount.amount'))->toBe(1000);
    }

    $results = placeAtOnce(array_map(static fn (array $checkout, int $index): array => ['token' => $checkout['token'], 'checkout' => $checkout['id'], 'version' => $checkout['version'], 'key' => "last-use-000{$index}"], $checkouts, array_keys($checkouts)));

    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([201, 409]);

    $refused = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 409))[0]['body'];
    expect($refused['code'])->toBe('checkout_quote_changed')
        ->and($refused['data']['totals']['discount']['amount'])->toBe(0)
        ->and($this->store->run(static fn (): array => [Order::query()->count(), PromotionRedemption::query()->where('status', RedemptionStatus::Reserved->value)->count()]))->toBe([1, 1]);
});
