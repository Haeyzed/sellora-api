<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Decimal;
use App\Tenant\Checkout\Actions\PlaceReplacementOrder;
use App\Tenant\Checkout\Actions\QuoteReplacementOrder;
use App\Tenant\Checkout\Enums\PaymentHoldStatus;
use App\Tenant\Checkout\Events\ExchangeCreditCommitted;
use App\Tenant\Checkout\Events\ExchangeCreditReleased;
use App\Tenant\Checkout\Exceptions\ReplacementCannotBePlacedException;
use App\Tenant\Checkout\Exceptions\ReplacementQuoteChangedException;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderDiscount;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Returns\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18c: a replacement order for a return in an exchange, priced and
 * placed through checkout's own public steps. Today's prices with today's
 * automatic promotions (never a code), in the order's currency at today's
 * rate, sent to the order's address; a free like-for-like line costs no more
 * than the returned units did, and a delivery the buyer doesn't pay for
 * costs nothing. The returned items credit the smaller of what they are
 * worth and what it costs, held on the order as the exchange credit, which
 * follows every hold's lifecycle. It can't be placed out of stock, in a
 * currency no longer offered, or for a quote staff weren't shown.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->original = returnableOrder([[10000, 1]]);
    $this->return = requestedReturn($this->original);
    $this->method = replacementShippingMethod(1500);
});

afterEach(function (): void {
    deleteAllStores();
});

it('prices a dearer replacement at today\'s price with today\'s automatic promotions and never a code, crediting what the returned items are worth', function (): void {
    [$return, $method] = [$this->return, $this->method];

    [$settlement, $discounts] = $this->store->run(static function () use ($return, $method): array {
        Promotion::factory()->create(['percent' => Decimal::of(10)]);
        $quote = app(QuoteReplacementOrder::class)->handle(replacementRequest($return, [[cartVariant(12000, stock: 5), 1]], 10000, $method));

        return [replacementSettlement($quote), $quote->basket?->discounts->total()->minorAmount()];
    });

    // 12000 less 10% is 10800, plus 1500 delivery: 12300. The returned item credits 10000; 2300 is due.
    expect($settlement)->toBe([10000, 12300, 10000, 2300, 0])
        ->and($discounts)->toBe(1200);
});

it('credits what a cheaper replacement costs, pays it at once, and leaves the rest to go back to the buyer', function (): void {
    Event::fake([ExchangeCreditCommitted::class]);
    [$return, $method] = [$this->return, $this->method];

    [$settlement, $order, $hold, $invoiced] = $this->store->run(static function () use ($return, $method): array {
        $request = replacementRequest($return, [[cartVariant(6000, stock: 5), 1]], 10000, $method, chargesShipping: false);
        $settlement = replacementSettlement(app(QuoteReplacementOrder::class)->handle($request));
        $placed = placeReplacement($request);

        return [$settlement, $placed->order->refresh(), $placed->credit?->refresh(), Invoice::query()->where('order_id', $placed->order->id)->exists()];
    });

    // The delivery isn't charged: it comes off as an allowance, so the replacement is 6000.
    expect($settlement)->toBe([10000, 6000, 6000, 0, 4000])
        ->and($order->replaces_return_id)->toBe($return->id)
        ->and($order->payment_status->value)->toBe('paid')
        ->and($order->is_test)->toBeFalse()
        ->and($hold?->method)->toBe('exchange_credit')
        ->and($hold?->reference)->toBe($return->public_id)
        ->and($hold?->amount->minorAmount())->toBe(6000)
        ->and($hold?->status)->toBe(PaymentHoldStatus::Committed)
        ->and($invoiced)->toBeTrue();

    Event::assertDispatched(ExchangeCreditCommitted::class, static fn (ExchangeCreditCommitted $event): bool => $event->hold->id === $hold?->id);
});

it('makes a free like-for-like replacement cost no more than the returned item, whatever today\'s price', function (): void {
    [$return, $method] = [$this->return, $this->method];

    [$settlement, $discounts] = $this->store->run(static function () use ($return, $method): array {
        // The same item costs 12000 today; the buyer paid 10000 for it.
        $placed = placeReplacement(replacementRequest($return, [[cartVariant(12000, stock: 5), 1, 10000]], 10000, $method, chargesShipping: false));
        $quote = app(QuoteReplacementOrder::class)->handle(replacementRequest($return, [[cartVariant(12000, stock: 5), 1, 10000]], 10000, $method, chargesShipping: false));

        return [
            replacementSettlement($quote),
            OrderDiscount::query()->where('order_id', $placed->order->id)->orderBy('id')->get()->map(static fn (OrderDiscount $discount): array => [$discount->source, $discount->class->value, $discount->discount->minorAmount()])->all(),
        ];
    });

    expect($settlement)->toBe([10000, 10000, 10000, 0, 0])
        ->and($discounts)->toBe([['exchange', 'product', 2000], ['exchange', 'shipping', 1500]]);
});

it('never makes a free like-for-like line cost more than the returned item where tax is added on top', function (): void {
    [$return, $method] = [$this->return, $this->method];

    $quote = $this->store->run(static function () use ($return, $method) {
        App\Tenant\Settings\Models\StoreSettings::query()->update(['tax_mode' => 'exclusive']);
        (new App\Tenant\Pricing\Models\TaxRate)->forceFill([
            'tax_class_id' => App\Tenant\Pricing\Models\TaxClass::query()->where('is_default', true)->sole()->id,
            'name' => 'VAT', 'country_code' => 'NG', 'rate' => Decimal::of('7.5'),
        ])->save();

        return app(QuoteReplacementOrder::class)->handle(replacementRequest($return, [[cartVariant(12345, stock: 5), 1, 9999]], 9999, $method, chargesShipping: false));
    });

    expect($quote->basket?->priced->tax()->isPositive())->toBeTrue()
        ->and($quote->total->isGreaterThan(App\Shared\Money\Money::ofMinor(9999, 'NGN')))->toBeFalse()
        ->and($quote->difference->minorAmount())->toBeLessThanOrEqual(1);
});

it('leaves a dearer replacement owing the rest, and gives the credit back when it expires unpaid', function (): void {
    Event::fake([ExchangeCreditReleased::class]);
    [$return, $method] = [$this->return, $this->method];

    [$before, $after] = $this->store->run(static function () use ($return, $method): array {
        $placed = placeReplacement(replacementRequest($return, [[cartVariant(12000, stock: 5), 1]], 10000, $method));
        $before = [$placed->order->refresh()->payment_status->value, $placed->credit?->refresh()->status];
        app(CancelOrder::class)->handle($placed->order, CancellationReason::PaymentExpired, null);

        return [$before, CheckoutPaymentHold::query()->findOrFail($placed->credit?->id)->status];
    });

    expect($before[0])->not->toBe('paid')
        ->and($before[1])->toBe(PaymentHoldStatus::Held)
        ->and($after)->toBe(PaymentHoldStatus::Released);

    Event::assertDispatched(ExchangeCreditReleased::class);
});

it('places once: confirming again returns the same replacement', function (): void {
    [$return, $method] = [$this->return, $this->method];

    [$first, $again, $count] = $this->store->run(static function () use ($return, $method): array {
        $request = replacementRequest($return, [[cartVariant(6000, stock: 5), 1]], 10000, $method);
        $first = placeReplacement($request);

        return [$first, app(PlaceReplacementOrder::class)->handle($request, 'any hash'), Order::query()->where('replaces_return_id', $return->id)->count()];
    });

    expect([$first->isNew, $again->isNew])->toBe([true, false])
        ->and($again->order->id)->toBe($first->order->id)
        ->and($again->credit?->id)->toBe($first->credit?->id)
        ->and($count)->toBe(1);
});

it('refuses a quote staff weren\'t shown, and places nothing', function (): void {
    [$return, $method] = [$this->return, $this->method];

    $this->store->run(static function () use ($return, $method): void {
        $variant = cartVariant(6000, stock: 5);
        $request = replacementRequest($return, [[$variant, 1]], 10000, $method);
        $shown = app(QuoteReplacementOrder::class)->handle($request);
        $variant->forceFill(['price' => App\Shared\Money\Money::ofMinor(7000, 'NGN')])->save();

        expect(fn () => app(PlaceReplacementOrder::class)->handle($request, $shown->hash))->toThrow(ReplacementQuoteChangedException::class)
            ->and(Order::query()->where('replaces_return_id', $return->id)->exists())->toBeFalse();
    });
});

it('can\'t place a replacement that is out of stock, no longer for sale, or in a currency no longer offered', function (Closure $request, string $code): void {
    $return = $this->return;

    $this->store->run(static function () use ($request, $return, $code): void {
        $replacement = $request($return, test()->method);
        $quote = app(QuoteReplacementOrder::class)->handle($replacement);

        expect($quote->issues)->toBe([$code])
            ->and($quote->canBePlaced())->toBeFalse();

        try {
            app(PlaceReplacementOrder::class)->handle($replacement, $quote->hash);
            test()->fail('It was placed.');
        } catch (ReplacementCannotBePlacedException $exception) {
            expect($exception->errorCode())->toBe($code)
                ->and($exception->status())->toBe(409);
        }

        expect(Order::query()->where('replaces_return_id', $return->id)->exists())->toBeFalse();
    });
})->with([
    'out of stock' => [fn (OrderReturn $return, $method) => replacementRequest($return, [[cartVariant(6000, stock: 0), 1]], 10000, $method), 'replacement_out_of_stock'],
    'no longer for sale' => [function (OrderReturn $return, $method) {
        $variant = cartVariant(6000, stock: 5);
        $variant->product->forceFill(['status' => App\Tenant\Catalog\Enums\ProductStatus::Archived])->save();

        return replacementRequest($return, [[$variant->refresh(), 1]], 10000, $method);
    }, 'replacement_not_available'],
    'currency no longer offered' => [function (OrderReturn $return, $method) {
        $original = Order::query()->findOrFail($return->order_id);
        $original->setAttribute('currency', 'USD');

        return replacementRequest($return, [[cartVariant(6000, stock: 5), 1]], 10000, $method, original: $original);
    }, 'replacement_currency_not_offered'],
]);

it('can\'t place it once the stock ran out after the quote was shown', function (): void {
    [$return, $method] = [$this->return, $this->method];

    $this->store->run(static function () use ($return, $method): void {
        $variant = cartVariant(6000, stock: 1);
        $request = replacementRequest($return, [[$variant, 1]], 10000, $method);
        $shown = app(QuoteReplacementOrder::class)->handle($request);
        app(ReserveStock::class)->handle(new StockHolder('checkout', 'someone-else'), [new StockLine($variant, 1)], CarbonImmutable::now()->addMinutes(15));

        expect(fn () => app(PlaceReplacementOrder::class)->handle($request, $shown->hash))->toThrow(ReplacementCannotBePlacedException::class, 'replacement_out_of_stock')
            ->and(Order::query()->where('replaces_return_id', $return->id)->exists())->toBeFalse();
    });
});

it('makes a test order\'s replacement a test order, which uses no invoice number', function (): void {
    $original = returnableOrder([[10000, 1]], isTest: true);
    $return = requestedReturn($original);
    $method = $this->method;

    [$order, $invoiced] = $this->store->run(static function () use ($return, $method): array {
        $placed = placeReplacement(replacementRequest($return, [[cartVariant(6000, stock: 5), 1]], 10000, $method, chargesShipping: false));

        return [$placed->order->refresh(), Invoice::query()->where('order_id', $placed->order->id)->whereNotNull('sequence_number')->exists()];
    });

    expect($order->is_test)->toBeTrue()
        ->and($order->payment_status->value)->toBe('paid')
        ->and($invoiced)->toBeFalse();
});

it('leaves a paid replacement that is cancelled owing its credit for staff, never giving it back silently', function (): void {
    [$return, $method] = [$this->return, $this->method];

    [$order, $hold] = $this->store->run(static function () use ($return, $method): array {
        $placed = placeReplacement(replacementRequest($return, [[cartVariant(6000, stock: 5), 1]], 10000, $method, chargesShipping: false));
        app(CancelOrder::class)->handle($placed->order->refresh(), CancellationReason::Staff, AccountReference::to(StaffMember::query()->firstOrFail()));

        return [$placed->order->refresh(), $placed->credit?->refresh()];
    });

    expect($order->status->value)->toBe('cancelled')
        ->and($order->refund_due)->toBeTrue()
        ->and($hold?->status)->toBe(PaymentHoldStatus::Committed)
        ->and($hold?->refund_failure)->toBe('payment_method_cannot_refund');
});
