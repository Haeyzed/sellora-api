<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Models\PromotionCode;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 12 with Step 11's rules: a cart holds one promotion code, alongside
 * the store's automatic promotions. Every code that can't be used gets the
 * same answer. A usable code is kept while it doesn't apply yet, with the
 * reason. Codes are limited per cart (or per customer) under a much higher
 * ceiling per IP address, so shoppers sharing an address don't block each
 * other.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cart-store', ['country_code' => 'NG']);
    $this->variant = $this->store->run(static function (): string {
        runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(20), 'min_subtotal' => 2000])->codes()->create(['code' => 'TWENTY']);
        runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(5), 'is_active' => false])->codes()->create(['code' => 'PAUSED']);

        return cartVariant(1000)->public_id;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps a usable code that doesn\'t apply yet, says why, and applies it once the cart qualifies', function (): void {
    $token = newGuestCart();
    cartRequest('POST', '/lines', ['variant' => $this->variant], token: $token)->assertOk();

    cartRequest('PUT', '/code', ['code' => ' twenty '], token: $token, ifMatch: '"2"')->assertOk()
        ->assertJsonPath('data.code', ['code' => 'TWENTY', 'applied' => false, 'reason' => 'minimum_not_met'])
        ->assertJsonPath('data.totals.discount.amount', 0);

    cartRequest('POST', '/lines', ['variant' => $this->variant], token: $token)->assertOk()
        ->assertJsonPath('data.code', ['code' => 'TWENTY', 'applied' => true, 'reason' => null])
        ->assertJsonPath('data.totals.discount.amount', 400)
        ->assertJsonPath('data.discounts.0.from_code', true);
});

it('gives every code that can\'t be used the same answer, and keeps the code the cart had', function (): void {
    $token = newGuestCart();
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $token, ifMatch: '"1"')->assertOk();

    foreach (['PAUSED', 'NO-SUCH-CODE', '!!'] as $code) {
        cartRequest('PUT', '/code', ['code' => $code], token: $token, ifMatch: '"2"')->assertUnprocessable()
            ->assertJsonPath('code', 'code_not_valid')->assertJsonPath('message', __('errors.code_not_valid'));
    }

    cartRequest('GET', token: $token)->assertJsonPath('data.version', 2)
        // The cart is empty, so there is nothing for it to apply to yet.
        ->assertJsonPath('data.code', ['code' => 'TWENTY', 'applied' => false, 'reason' => 'no_eligible_items']);
});

it('holds one code at a time, needs the cart version to change it, and leaves automatic promotions alone when it goes', function (): void {
    $token = newGuestCart();
    $this->store->run(static function (): void {
        runningPromotion(['method' => PromotionMethod::Code, 'percent' => Decimal::of(50)])->codes()->create(['code' => 'HALF']);
        runningPromotion(['percent' => Decimal::of(10), 'combines_with_order_discounts' => false]);
    });
    cartRequest('POST', '/lines', ['variant' => $this->variant, 'quantity' => 3], token: $token)->assertOk()->assertJsonPath('data.totals.discount.amount', 300);

    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $token)->assertStatus(428);
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $token, ifMatch: '"2"')->assertOk()->assertJsonPath('data.totals.discount.amount', 600);
    cartRequest('PUT', '/code', ['code' => 'HALF'], token: $token, ifMatch: '"3"')->assertOk()->assertJsonPath('data.code.code', 'HALF')->assertJsonPath('data.totals.discount.amount', 1500);

    cartRequest('DELETE', '/code', token: $token)->assertStatus(428);
    cartRequest('DELETE', '/code', token: $token, ifMatch: '"4"')->assertOk()->assertJsonPath('data.code', null)->assertJsonPath('data.totals.discount.amount', 300);
});

it('says a code can no longer be used when it stops working after it was entered', function (): void {
    $token = newGuestCart();
    cartRequest('POST', '/lines', ['variant' => $this->variant, 'quantity' => 2], token: $token)->assertOk();
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $token, ifMatch: '"2"')->assertOk()->assertJsonPath('data.code.applied', true);

    $this->store->run(static fn () => PromotionCode::query()->where('code', 'TWENTY')->update(['is_active' => false]));

    cartRequest('GET', token: $token)->assertJsonPath('data.code', ['code' => 'TWENTY', 'applied' => false, 'reason' => 'code_not_valid'])->assertJsonPath('data.totals.discount.amount', 0);
});

it('limits codes per cart, so many carts behind one address can each enter codes while one cart hammering is stopped', function (): void {
    config(['api.rate_limits.coupon_redemption' => 2, 'api.rate_limits.coupon_redemption_ip_ceiling' => 6]);
    [$first, $second, $third] = [newGuestCart(), newGuestCart(), newGuestCart()];

    cartRequest('PUT', '/code', ['code' => 'NOPE-1'], token: $first, ifMatch: '"1"')->assertUnprocessable();
    cartRequest('PUT', '/code', ['code' => 'NOPE-2'], token: $first, ifMatch: '"1"')->assertUnprocessable();
    // Even the right code is refused to the cart that kept guessing.
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $first, ifMatch: '"1"')->assertTooManyRequests()->assertJsonPath('code', 'too_many_code_attempts');

    // The others behind the same address carry on.
    cartRequest('PUT', '/code', ['code' => 'NOPE-3'], token: $second, ifMatch: '"1"')->assertUnprocessable();
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $second, ifMatch: '"1"')->assertOk();
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: $third, ifMatch: '"1"')->assertOk();

    // A flood of new carts from that address still hits the ceiling, which another address doesn't share.
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: newGuestCart(), ifMatch: '"1"')->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], token: newGuestCart(ip: '198.51.100.7'), ifMatch: '"1"', ip: '198.51.100.7')->assertOk();
});

it('limits a signed-in customer\'s codes per customer, wherever they enter them from', function (): void {
    config(['api.rate_limits.coupon_redemption' => 2]);
    $ada = $this->store->run(static fn (): Customer => Customer::factory()->create());
    cartRequest('POST', as: $ada)->assertCreated();

    cartRequest('PUT', '/code', ['code' => 'NOPE-1'], as: $ada, ifMatch: '"1"')->assertUnprocessable();
    cartRequest('PUT', '/code', ['code' => 'NOPE-2'], as: $ada, ifMatch: '"1"', ip: '198.51.100.7')->assertUnprocessable();
    cartRequest('PUT', '/code', ['code' => 'TWENTY'], as: $ada, ifMatch: '"1"', ip: '192.0.2.44')->assertTooManyRequests()->assertJsonPath('code', 'too_many_code_attempts');
});
