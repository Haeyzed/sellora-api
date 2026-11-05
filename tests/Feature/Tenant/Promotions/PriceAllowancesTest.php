<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Tenant\Orders\Data\OrderSource;
use App\Tenant\Orders\Models\OrderDiscount;
use App\Tenant\Promotions\Data\DiscountedBasket;
use App\Tenant\Promotions\Data\DiscountProposal;
use App\Tenant\Promotions\Data\PriceAllowances;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18c: a replacement order's allowances, the fixed reductions an
 * exchange agreed, are taken off after the automatic promotions, never more
 * than a line or the delivery still costs, before tax, and each is recorded
 * as a discount from the source "exchange". Only replacement orders give
 * them (the architecture rule in DependencyRulesTest).
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('takes allowances off after the automatic promotions, as discounts of their own', function (): void {
    $basket = $this->store->run(static function (): DiscountedBasket {
        runningPromotion(['percent' => Decimal::of(10)]);

        return pricedWithAllowances([[promotedVariant(10000), 1]], ['line-1' => 3000], shipping: 1500, shippingAllowance: 500);
    });

    $exchange = array_values(array_filter($basket->discounts->applied, static fn (DiscountProposal $discount): bool => $discount->source === PriceAllowances::SOURCE));

    expect(lineDiscountsOf($basket))->toBe(['line-1' => 4000])
        ->and($basket->discounts->shippingDiscount->minorAmount())->toBe(500)
        ->and(array_map(static fn (DiscountProposal $discount): array => [$discount->class->value, $discount->total('NGN')->minorAmount(), $discount->label], $exchange))
        ->toBe([['product', 3000, 'Exchange allowance'], ['shipping', 500, 'Exchange allowance']])
        // Tax is worked out on what is paid: 10000 - 4000 for the line, 1500 - 500 for the delivery.
        ->and($basket->priced->total()->minorAmount())->toBe(7000);
});

it('never takes off more than a line or the delivery still costs', function (): void {
    $basket = $this->store->run(static function (): DiscountedBasket {
        runningPromotion(['percent' => Decimal::of(10)]);

        return pricedWithAllowances([[promotedVariant(10000), 1]], ['line-1' => 50000], shipping: 1500, shippingAllowance: 9000);
    });

    expect(lineDiscountsOf($basket))->toBe(['line-1' => 10000])
        ->and($basket->discounts->shippingDiscount->minorAmount())->toBe(1500)
        ->and($basket->priced->total()->minorAmount())->toBe(0);
});

it('refuses an allowance that doesn\'t fit the lines', function (array $lines, string $message): void {
    expect(fn () => $this->store->run(static fn () => pricedWithAllowances([[promotedVariant(10000), 1]], $lines)))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'for no line' => [['line-9' => 100], 'isn\'t priced'],
    'in another currency' => [['line-1' => Money::ofMinor(100, 'USD')], 'positive amount in NGN'],
    'negative' => [['line-1' => -100], 'positive amount in NGN'],
]);

it('places an order whose exchange allowances are recorded as its discounts', function (): void {
    $discounts = $this->store->run(static function (): array {
        $variant = cartVariant(10000, stock: 5);
        $draft = orderDraft([[$variant, 1]], orderShippingMethod(), source: OrderSource::checkout('allowance-checkout'));
        $basket = pricedWithAllowances([[$variant, 1]], ['line-1' => 2500], shipping: 1500, shippingAllowance: 1500);
        $withAllowances = new App\Tenant\Orders\Data\OrderDraft($draft->source, $basket, $draft->buyer, null, $draft->shippingAddress, $draft->shipping, $draft->stockHolder, $draft->promotionHolder, 'en');
        $order = placeOrder($withAllowances);

        return OrderDiscount::query()->where('order_id', $order->id)->orderBy('id')->get()->map(static fn (OrderDiscount $discount): array => [$discount->source, $discount->class->value, $discount->order_line_id === null, $discount->discount->minorAmount()])->all();
    });

    expect($discounts)->toBe([['exchange', 'product', false, 2500], ['exchange', 'shipping', true, 1500]]);
});
