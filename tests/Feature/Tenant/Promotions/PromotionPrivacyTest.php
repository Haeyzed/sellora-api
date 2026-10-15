<?php

declare(strict_types=1);

use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\RetentionRegistry;
use App\Tenant\Customers\Actions\EraseCustomer;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Promotions\Actions\SettlePromotionUses;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\RedemptionHolder;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Models\PromotionRedemption;
use App\Tenant\Promotions\ReleasedPromotionUseRetention;
use Carbon\CarbonImmutable;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 11, sections 3.3 and 13: the promotions a customer used are part of
 * their personal data, by account and by email (guest checkouts included).
 * Erasure keeps each use, which usage counts and orders need, but not who it
 * was. Uses that were given back are deleted after the retention period;
 * reserved and committed ones never are.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('exports the promotions a customer used, by account and as a guest with their email', function (): void {
    $this->store->run(static function (): void {
        $promotion = runningPromotion(['method' => PromotionMethod::Code, 'title' => ['en' => 'Ten off']]);
        $promotion->codes()->create(['code' => 'TEN']);
        $ada = Customer::factory()->create(['email' => 'ada@example.com']);
        $basket = discountedBasket([[promotedVariant(1000), 1]], code: 'TEN');

        reservePromotionUses('as-customer', $basket, DiscountBuyer::customer($ada));
        reservePromotionUses('as-guest', $basket, DiscountBuyer::guest('ada@example.com'));
        reservePromotionUses('someone-else', $basket, DiscountBuyer::guest('grace@example.com'));

        $uses = app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($ada))['promotion_uses'];

        expect($uses)->toHaveCount(2)
            ->and($uses[0])->toMatchArray(['promotion' => 'Ten off', 'code' => 'TEN', 'status' => 'reserved', 'discount' => ['amount' => 100, 'currency' => 'NGN']]);
    });
});

it('keeps each use when the customer is erased, but not who it was', function (): void {
    $this->store->run(static function (): void {
        runningPromotion(['usage_limit' => 5]);
        $ada = Customer::factory()->create(['email' => 'ada@example.com']);
        $basket = discountedBasket([[promotedVariant(1000), 1]]);
        reservePromotionUses('as-customer', $basket, DiscountBuyer::customer($ada));
        reservePromotionUses('as-guest', $basket, DiscountBuyer::guest('ada@example.com'));
        reservePromotionUses('someone-else', $basket, DiscountBuyer::guest('grace@example.com'));

        app(EraseCustomer::class)->erase($ada);

        expect(PromotionRedemption::query()->orderBy('id')->get(['holder_id', 'customer_id', 'email'])->toArray())->toBe([
            ['holder_id' => 'as-customer', 'customer_id' => null, 'email' => null],
            ['holder_id' => 'as-guest', 'customer_id' => null, 'email' => null],
            ['holder_id' => 'someone-else', 'customer_id' => null, 'email' => 'grace@example.com'],
        ]);
    });
});

it('deletes uses that were given back once the retention period has passed, and never reserved or committed ones', function (): void {
    config(['retention.periods.released_promotion_uses' => 30]);

    $this->store->run(function (): void {
        runningPromotion();
        $basket = discountedBasket([[promotedVariant(1000), 1]]);
        reservePromotionUses('abandoned', $basket);
        reservePromotionUses('paid', $basket);
        reservePromotionUses('still-open', $basket);
        app(SettlePromotionUses::class)->release(new RedemptionHolder('checkout', 'abandoned'));
        app(SettlePromotionUses::class)->commit(new RedemptionHolder('checkout', 'paid'), new RedemptionHolder('order', 'order-1'));
        PromotionRedemption::query()->where('holder_id', 'still-open')->update(['expires_at' => now()->addDays(60)]);

        $this->travel(31)->days();

        expect(app(ReleasedPromotionUseRetention::class)->purgeOlderThan(CarbonImmutable::now()->subDays(30)))->toBe(1)
            ->and(PromotionRedemption::query()->orderBy('id')->pluck('holder_id')->all())->toBe(['order-1', 'still-open'])
            ->and(array_map(get_class(...), app(RetentionRegistry::class)->policiesFor(true)))->toContain(ReleasedPromotionUseRetention::class);
    });
});
