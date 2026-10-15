<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Promotions\Contracts\OrderHistory;
use App\Tenant\Promotions\Data\DiscountBuyer;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\DiscountSourceRegistry;
use App\Tenant\Promotions\Enums\PromotionAppliesTo;
use App\Tenant\Promotions\Enums\PromotionType;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Promotions\FlatDiscountSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 11: how discounts reach a price. Each promotion is worked out on the
 * lines it covers, in the basket's currency at the checkout's rate, spread
 * across lines by largest remainder and taken off before tax. Product
 * discounts first (one per line), then the order discount on what remains,
 * then shipping; two apply together only when both say so, and when they
 * don't all combine the set that saves the customer most wins. Codes that don't
 * apply say why. Modules add discount sources that are used only while they
 * are enabled. 0- and 3-decimal currencies round to their own units.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes a percentage off the order once and spreads it by largest remainder, so the lines add up exactly', function (): void {
    $this->store->run(static function (): void {
        runningPromotion(['percent' => App\Shared\Money\Decimal::of(10)]);

        $basket = discountedBasket([[promotedVariant(1999), 1], [promotedVariant(3001), 2]]);

        // 10% of 8001 is 800.1, rounded half-up to 800; 199.875 and 600.125 by largest remainder.
        expect(lineDiscountsOf($basket))->toBe(['line-1' => 200, 'line-2' => 600])
            ->and(basketTotals($basket->priced))->toBe([8001, 800, 0, 7201])
            ->and($basket->discounts->applied)->toHaveCount(1);
    });
});

it('rounds to each currency\'s own units, with no decimals and with three', function (string $country, string $currency): void {
    if ($country === 'KW') {
        seedWorldCountry('KW', 'Kuwait', '965', 'KWD', 'Asia/Kuwait');
    }

    $store = createStore('rounding-store', ['country_code' => $country, 'currency_code' => $currency]);

    $store->run(static function () use ($currency): void {
        expect(App\Tenant\Settings\Models\StoreSettings::query()->sole()->currency_code)->toBe($currency);
        runningPromotion(['percent' => App\Shared\Money\Decimal::of('12.5')]);

        // 12.5% of 1999 smallest units is 249.875: 250 yen, 0.250 dinar, 2.50 naira.
        expect(lineDiscountsOf(discountedBasket([[promotedVariant(1999), 1]])))->toBe(['line-1' => 250]);
    });
})->with(['yen, no decimals' => ['JP', 'JPY'], 'dinar, three decimals' => ['KW', 'KWD'], 'naira, two decimals' => ['NG', 'NGN']]);

it('converts a fixed amount at the checkout\'s rate into the customer\'s currency, unless the store set an exact amount', function (string $currency, string $rate, int $expected, ?int $exact): void {
    $this->store->run(static function () use ($currency, $rate, $expected, $exact): void {
        offerStoreCurrency($currency, $rate);
        $promotion = runningPromotion(['type' => PromotionType::FixedAmount, 'percent' => null, 'discount' => 500000]);

        if ($exact !== null) {
            $promotion->currencyAmounts()->create(['currency_code' => $currency, 'discount' => App\Shared\Money\Money::ofMinor($exact, $currency)]);
        }

        // ₦100,000 converts to 9,600 yen or 20.000 dinar; ₦5,000 off to 480 yen or 1.000 dinar.
        expect(lineDiscountsOf(discountedBasket([[promotedVariant(10000000), 1]], currency: $currency)))->toBe(['line-1' => $expected]);
    });
})->with([
    'yen, converted' => ['JPY', '0.096', 480, null],
    'dinar, converted' => ['KWD', '0.0002', 1000, null],
    'yen, an exact amount set' => ['JPY', '0.096', 500, 500],
]);

it('never takes more off than the lines cost', function (): void {
    $this->store->run(static function (): void {
        runningPromotion(['type' => PromotionType::FixedAmount, 'percent' => null, 'discount' => 5000]);

        $basket = discountedBasket([[promotedVariant(1000), 1], [promotedVariant(2000), 1]]);

        expect(lineDiscountsOf($basket))->toBe(['line-1' => 1000, 'line-2' => 2000])
            ->and($basket->discounts->applied[0]->total('NGN')->minorAmount())->toBe(3000)
            ->and(basketTotals($basket->priced)[3])->toBe(0);
    });
});

it('discounts only the products, variants and categories a promotion covers, subcategories included and exclusions first', function (): void {
    $this->store->run(static function (): void {
        $clothing = Category::factory()->create();
        $shirts = Category::factory()->create(['parent_id' => $clothing->id]);
        $shirt = promotedVariant(1000, categories: [$shirts]);
        $hat = promotedVariant(1000);
        $socks = promotedVariant(1000, categories: [$clothing]);

        $promotion = runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(50)]);
        $promotion->categories()->attach($clothing->id, ['list' => 'include']);
        $promotion->variants()->attach($socks->id, ['list' => 'exclude']);

        expect(lineDiscountsOf(discountedBasket([[$shirt, 1], [$hat, 1], [$socks, 1]])))->toBe(['line-1' => 500, 'line-2' => 0, 'line-3' => 0]);
    });
});

it('leaves items on sale out unless the promotion includes them, and tells a code\'s user so', function (): void {
    $this->store->run(static function (): void {
        $promotion = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code]);
        $promotion->codes()->create(['code' => 'SAVE10']);
        $onSale = promotedVariant(1000, compareAt: 1500);

        $basket = discountedBasket([[$onSale, 1]], code: 'save10');
        expect(lineDiscountsOf($basket))->toBe(['line-1' => 0])
            ->and($basket->discounts->codeReason)->toBe('sale_items_excluded');

        $promotion->update(['includes_sale_items' => true]);
        $basket = discountedBasket([[$onSale, 1]], code: 'save10');
        expect(lineDiscountsOf($basket))->toBe(['line-1' => 100])
            ->and($basket->discounts->codeApplies())->toBeTrue();
    });
});

it('needs the minimum subtotal or quantity of eligible items, before discounts', function (array $minimums, array $lines, ?string $reason): void {
    $this->store->run(static function () use ($minimums, $lines, $reason): void {
        $promotion = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code, ...$minimums]);
        $promotion->codes()->create(['code' => 'BIG']);
        $basket = discountedBasket(array_map(static fn (array $line): array => [promotedVariant($line[0]), $line[1]], $lines), code: 'BIG');

        expect($basket->discounts->codeReason)->toBe($reason);
    });
})->with([
    'below the subtotal' => [['min_subtotal' => 5000], [[2000, 2]], 'minimum_not_met'],
    'at the subtotal' => [['min_subtotal' => 5000], [[2500, 2]], null],
    'below the quantity' => [['min_quantity' => 3], [[100, 2]], 'minimum_not_met'],
    'at the quantity' => [['min_quantity' => 3], [[100, 1], [100, 2]], null],
]);

it('gives buy X get Y on the cheapest qualifying units, as many times as there are enough', function (array $promotion, array $lines, array $expected): void {
    $this->store->run(static function () use ($promotion, $lines, $expected): void {
        runningPromotion(['type' => PromotionType::BuyXGetY, 'applies_to' => null, 'percent' => App\Shared\Money\Decimal::of(100), 'buy_quantity' => 2, 'get_quantity' => 1, ...$promotion]);

        expect(lineDiscountsOf(discountedBasket(array_map(static fn (array $line): array => [promotedVariant($line[0]), $line[1]], $lines))))->toBe($expected);
    });
})->with([
    'buy 2 get the cheapest free' => [[], [[3000, 1], [1000, 1], [2000, 1]], ['line-1' => 0, 'line-2' => 1000, 'line-3' => 0]],
    'twice with six' => [[], [[1000, 6]], ['line-1' => 2000]],
    'but at most once' => [['max_applications' => 1], [[1000, 6]], ['line-1' => 1000]],
    'half price instead' => [['percent' => App\Shared\Money\Decimal::of(50)], [[1000, 3]], ['line-1' => 500]],
    'not enough to buy' => [[], [[1000, 2]], ['line-1' => 0]],
]);

it('gives buy X get Y on other items when the promotion names what to get', function (): void {
    $this->store->run(static function (): void {
        $shirt = promotedVariant(3000);
        $socks = promotedVariant(500);
        $promotion = runningPromotion(['type' => PromotionType::BuyXGetY, 'applies_to' => null, 'percent' => App\Shared\Money\Decimal::of(100), 'buy_quantity' => 2, 'get_quantity' => 1]);
        $promotion->products()->attach($shirt->product_id, ['list' => 'include']);
        $promotion->products()->attach($socks->product_id, ['list' => 'get']);

        expect(lineDiscountsOf(discountedBasket([[$shirt, 2], [$socks, 1]])))->toBe(['line-1' => 0, 'line-2' => 500])
            ->and(lineDiscountsOf(discountedBasket([[$shirt, 1], [$socks, 1]])))->toBe(['line-1' => 0, 'line-2' => 0]);
    });
});

it('takes the delivery charge off with free shipping, up to its cap, and before tax', function (): void {
    $this->store->run(static function (): void {
        runningPromotion(['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null, 'max_discount' => 2000]);
        $shirt = promotedVariant(1000);

        $basket = discountedBasket([[$shirt, 1]], shipping: 2500);
        expect($basket->discounts->shippingDiscount->minorAmount())->toBe(2000)
            ->and($basket->priced->total()->minorAmount())->toBe(1500);

        // Before the delivery charge is known it still applies, at nothing yet.
        $basket = discountedBasket([[$shirt, 1]]);
        expect($basket->discounts->applied)->toHaveCount(1)
            ->and($basket->discounts->shippingDiscount->isZero())->toBeTrue();
    });
});

it('gives each line one product discount, the one worth most, and works out the next on the lines still free', function (bool $combine, array $expected): void {
    $this->store->run(static function () use ($combine, $expected): void {
        $a = promotedVariant(1000);
        $b = promotedVariant(1000);
        $half = runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(50), 'combines_with_product_discounts' => $combine]);
        $half->variants()->attach($a->id, ['list' => 'include']);
        runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(20), 'combines_with_product_discounts' => $combine]);

        expect(lineDiscountsOf(discountedBasket([[$a, 1], [$b, 1]])))->toBe($expected);
    });
})->with([
    'when both combine with product discounts' => [true, ['line-1' => 500, 'line-2' => 200]],
    'when they don\'t' => [false, ['line-1' => 500, 'line-2' => 0]],
]);

it('works out the order discount on what the lines cost after product discounts, only when both allow it', function (bool $productAllows, bool $orderAllows, array $expected): void {
    $this->store->run(static function () use ($productAllows, $orderAllows, $expected): void {
        $a = promotedVariant(1000);
        $b = promotedVariant(1000);
        $half = runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(50), 'combines_with_order_discounts' => $productAllows]);
        $half->variants()->attach($a->id, ['list' => 'include']);
        runningPromotion(['percent' => App\Shared\Money\Decimal::of(10), 'combines_with_product_discounts' => $orderAllows]);

        expect(lineDiscountsOf(discountedBasket([[$a, 1], [$b, 1]])))->toBe($expected);
    });
})->with([
    // 10% of 500 + 1000 = 150, spread 50 and 100.
    'combined' => [true, true, ['line-1' => 550, 'line-2' => 100]],
    // They don't combine, and the product discount (500) saves more than the order discount (200).
    'not combined' => [false, false, ['line-1' => 500, 'line-2' => 0]],
    'only the product discount allows it' => [true, false, ['line-1' => 500, 'line-2' => 0]],
    'only the order discount allows it' => [false, true, ['line-1' => 500, 'line-2' => 0]],
]);

it('gives the discounts that save the customer most when they don\'t all combine, and tells a code that loses why', function (int $codePercent, array $expected, ?string $reason): void {
    $this->store->run(static function () use ($codePercent, $expected, $reason): void {
        runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(5)]);
        $code = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code, 'percent' => App\Shared\Money\Decimal::of($codePercent)]);
        $code->codes()->create(['code' => 'ORDER']);

        $basket = discountedBasket([[promotedVariant(1000), 1]], code: 'ORDER');

        expect(lineDiscountsOf($basket))->toBe($expected)
            ->and($basket->discounts->applied)->toHaveCount(1)
            ->and($basket->discounts->codeReason)->toBe($reason);
    });
})->with([
    // The 5% product discount came first under the old rule, though the order code saves more.
    'a larger order code wins' => [10, ['line-1' => 100], null],
    'a smaller one loses' => [3, ['line-1' => 50], 'better_discount_applied'],
]);

it('gives a tie between discounts that don\'t combine to the oldest promotion', function (bool $productIsOlder, string $class): void {
    $this->store->run(static function () use ($productIsOlder, $class): void {
        $product = static fn () => runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(10)]);
        $order = static fn () => runningPromotion(['percent' => App\Shared\Money\Decimal::of(10)]);
        foreach ($productIsOlder ? [$product, $order] : [$order, $product] as $create) {
            $create();
        }

        $basket = discountedBasket([[promotedVariant(1000), 1]]);

        expect(array_map(static fn (DiscountProposal $discount): string => $discount->class->value, $basket->discounts->applied))->toBe([$class])
            ->and(lineDiscountsOf($basket))->toBe(['line-1' => 100]);
    });
})->with([
    'the product discount is older' => [true, 'product'],
    'the order discount is older' => [false, 'order'],
]);

it('never saves the customer less than taking product discounts first did', function (array $promotions, int $earlierClassWins, int $expected): void {
    $this->store->run(static function () use ($promotions, $earlierClassWins, $expected): void {
        $a = promotedVariant(1000);
        $b = promotedVariant(1000);

        foreach ($promotions as $attributes) {
            $only = $attributes['only_a'] ?? false;
            unset($attributes['only_a']);
            $promotion = runningPromotion([...$attributes, 'percent' => $attributes['percent'] === null ? null : App\Shared\Money\Decimal::of($attributes['percent'])]);

            if ($only) {
                $promotion->variants()->attach($a->id, ['list' => 'include']);
            }
        }

        $saved = discountedBasket([[$a, 1], [$b, 1]], shipping: 1000)->discounts->total()->minorAmount();

        expect($saved)->toBe($expected)->toBeGreaterThanOrEqual($earlierClassWins);
    });
})->with([
    // 50% off a, combining with orders, then 10% of what remains (150); a 20% order discount alone is only 400.
    'the old choice was already best' => [[
        ['applies_to' => PromotionAppliesTo::Products, 'percent' => 50, 'only_a' => true, 'combines_with_order_discounts' => true],
        ['percent' => 10, 'combines_with_product_discounts' => true],
        ['percent' => 20],
    ], 650, 650],
    // 5% of each line (100) blocked every order discount; 30% off the order alone saves 600.
    'an order discount alone saves more' => [[
        ['applies_to' => PromotionAppliesTo::Products, 'percent' => 5],
        ['percent' => 30],
    ], 100, 600],
    // 5% off a blocked shipping; free delivery (1000) combines with the 10% order discount (200).
    'a larger set without the product discount' => [[
        ['applies_to' => PromotionAppliesTo::Products, 'percent' => 5, 'only_a' => true],
        ['percent' => 10, 'combines_with_shipping_discounts' => true],
        ['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null, 'combines_with_order_discounts' => true],
    ], 50, 1200],
    // 30% off the order (600) blocked free delivery (1000); they don't combine, so only one applies.
    'free delivery alone saves more' => [[
        ['percent' => 30],
        ['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null],
    ], 600, 1000],
]);

it('still adds the winning discount up exactly in currencies with no decimals and with three', function (string $country, string $currency): void {
    if ($country === 'KW') {
        seedWorldCountry('KW', 'Kuwait', '965', 'KWD', 'Asia/Kuwait');
    }

    createStore('rounding-store', ['country_code' => $country, 'currency_code' => $currency])->run(static function (): void {
        runningPromotion(['applies_to' => PromotionAppliesTo::Products, 'percent' => App\Shared\Money\Decimal::of(5)]);
        $code = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code, 'percent' => App\Shared\Money\Decimal::of('12.5')]);
        $code->codes()->create(['code' => 'EIGHTH']);

        $basket = discountedBasket([[promotedVariant(1999), 1], [promotedVariant(3001), 2]], code: 'EIGHTH');

        // 12.5% of 8001 is 1000.125, so 1000; 249.875 and 750.25 by largest remainder. The product discount would be 400.
        expect(lineDiscountsOf($basket))->toBe(['line-1' => 250, 'line-2' => 750])
            ->and($basket->discounts->applied[0]->total($basket->discounts->currency)->minorAmount())->toBe(1000)
            ->and($basket->discounts->codeApplies())->toBeTrue();
    });
})->with(['yen, no decimals' => ['JP', 'JPY'], 'dinar, three decimals' => ['KW', 'KWD']]);

it('gives a code that can\'t be used now the same answer as one that doesn\'t exist', function (): void {
    $this->store->run(static function (): void {
        $code = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code, 'is_active' => false]);
        $code->codes()->create(['code' => 'PAUSED']);
        $variant = promotedVariant(1000);

        expect(discountedBasket([[$variant, 1]], code: 'PAUSED')->discounts->codeReason)->toBe('code_not_valid')
            ->and(discountedBasket([[$variant, 1]], code: 'NOSUCHCODE')->discounts->codeReason)->toBe('code_not_valid');
    });
});

it('gives first-order promotions only to buyers known to have no orders, failing closed until Orders can tell', function (): void {
    $this->store->run(function (): void {
        runningPromotion(['first_order_only' => true]);
        $variant = promotedVariant(1000);
        $customer = Customer::factory()->create();

        expect(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest(null))))->toBe(['line-1' => 0])
            ->and(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::customer($customer))))->toBe(['line-1' => 0]);

        $this->app->instance(OrderHistory::class, new class implements OrderHistory
        {
            public function hasOrdered(?Customer $customer, ?string $email): bool
            {
                return $email === 'returning@example.com';
            }
        });

        expect(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest('New@Example.com'))))->toBe(['line-1' => 100])
            ->and(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest('Returning@Example.com'))))->toBe(['line-1' => 0])
            ->and(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest(null))))->toBe(['line-1' => 0]);
    });
});

it('gives a promotion limited to customer groups only to customers in one of them', function (): void {
    $this->store->run(static function (): void {
        $vip = CustomerGroup::factory()->create();
        runningPromotion()->customerGroups()->attach($vip->id);
        $variant = promotedVariant(1000);
        $member = Customer::factory()->create();
        $member->groups()->attach($vip->id);

        expect(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::customer($member))))->toBe(['line-1' => 100])
            ->and(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::customer(Customer::factory()->create()))))->toBe(['line-1' => 0])
            ->and(lineDiscountsOf(discountedBasket([[$variant, 1]], buyer: DiscountBuyer::guest('ada@example.com'))))->toBe(['line-1' => 0]);
    });
});

it('takes discounts off before tax, on tax-inclusive and tax-exclusive prices', function (string $country, int $price, string $rate, array $totals): void {
    $store = $country === 'NG' ? $this->store : createStore('exclusive-store', ['country_code' => $country, 'currency_code' => 'USD', 'timezone' => 'America/New_York']);

    $store->run(static function () use ($country, $price, $rate, $totals): void {
        addTaxRate('Standard', $country, null, $rate);
        runningPromotion();

        expect(basketTotals(discountedBasket([[promotedVariant($price), 1]], destination: new TaxAddress($country))->priced))->toBe($totals);
    });
})->with([
    // ₦107.50 with 7.5% VAT inside: 10% off leaves ₦96.75, of which ₦6.75 is VAT.
    'tax-inclusive' => ['NG', 10750, '7.5', [10750, 1075, 675, 9675]],
    // $100.00 before 5% tax: 10% off leaves $90.00, and $4.50 tax on top.
    'tax-exclusive' => ['US', 10000, '5', [10000, 1000, 450, 9450]],
]);

it('starts and ends promotions by the store\'s clock', function (): void {
    $this->store->run(static function (): void {
        // Midnight on 1 January 2030 in Lagos is 23:00 UTC the day before.
        runningPromotion(['starts_at' => CarbonImmutable::parse('2030-01-01 00:00', 'Africa/Lagos'), 'ends_at' => CarbonImmutable::parse('2030-01-02 00:00', 'Africa/Lagos')]);
        $variant = promotedVariant(1000);
        $at = static fn (string $utc): array => lineDiscountsOf(discountedBasket([[$variant, 1]], at: CarbonImmutable::parse($utc, 'UTC')));

        expect($at('2029-12-31 22:59'))->toBe(['line-1' => 0])
            ->and($at('2029-12-31 23:00'))->toBe(['line-1' => 100])
            ->and($at('2030-01-01 22:59'))->toBe(['line-1' => 100])
            ->and($at('2030-01-01 23:00'))->toBe(['line-1' => 0]);
    });
});

it('lets modules add discount sources, used only while the module is enabled for the store', function (): void {
    app(FeatureRegistry::class)->register(new FeatureDefinition('loyalty', FeatureKind::Module));
    app(DiscountSourceRegistry::class)->register(FlatDiscountSource::class, 'loyalty');
    $variant = $this->store->run(static fn () => promotedVariant(1000));
    $sources = fn (): array => $this->store->run(static fn (): array => array_map(static fn (DiscountProposal $discount): string => $discount->source, discountedBasket([[$variant, 1]])->discounts->applied));

    expect($sources())->toBe([]);

    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(entitled: ['loyalty'])));

    expect($sources())->toBe(['loyalty']);
});

it('lets staff preview what a basket gets, with the code they try, with promotions.view', function (): void {
    [$variant, $viewer, $outsider] = $this->store->run(static function (): array {
        $code = runningPromotion(['method' => App\Tenant\Promotions\Enums\PromotionMethod::Code, 'title' => ['en' => 'Ten off']]);
        $code->codes()->create(['code' => 'TEN']);
        $role = Role::findOrCreate('Marketing', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('promotions.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($role);

        return [promotedVariant(5000)->public_id, $viewer, StaffMember::factory()->create()];
    });

    promotionRequest('POST', '/preview', ['lines' => [['variant' => $variant, 'quantity' => 2]], 'code' => 'ten', 'shipping' => 1000], $viewer)
        ->assertOk()
        ->assertJsonPath('data.discounts.0.label', 'Ten off')
        ->assertJsonPath('data.discounts.0.class', 'order')
        ->assertJsonPath('data.discounts.0.from_code', true)
        ->assertJsonPath('data.discounts.0.amount.amount', 1000)
        ->assertJsonPath('data.basket.totals.total.amount', 10000)
        ->assertJsonPath('data.code', ['code' => 'ten', 'applied' => true, 'reason' => null]);

    promotionRequest('POST', '/preview', ['lines' => [['variant' => $variant, 'quantity' => 1]], 'code' => 'NOPE'], $viewer)
        ->assertOk()
        ->assertJsonPath('data.code', ['code' => 'NOPE', 'applied' => false, 'reason' => 'code_not_valid']);

    promotionRequest('POST', '/preview', ['lines' => [['variant' => $variant, 'quantity' => 1]]], $outsider)->assertForbidden();
});
