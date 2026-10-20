<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Actions\PlaceOrder;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Events\OrderPlaced;
use App\Tenant\Orders\Exceptions\InvalidOrderDraftException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Promotions\Contracts\OrderHistory;
use App\Tenant\Promotions\Enums\PromotionType;
use App\Tenant\Promotions\Enums\RedemptionStatus;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionRedemption;
use App\Tenant\Shipping\Data\ShippingOption;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14b: PlaceOrder turns a checkout into an order, for Checkout (Step 16)
 * to call. Everything is copied as it was charged: names in the buyer's
 * language, SKU, unit price, quantity, each discount per promotion, each tax
 * applied, the shipping line, the currency and exchange rate, the tax mode,
 * the addresses and the contact details. Nothing that changes later changes
 * the order. The checkout's stock hold and promotion uses become the order's.
 * Every order has a payment deadline. Placing a checkout twice returns the
 * same order.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
});

afterEach(function (): void {
    deleteAllStores();
});

it('copies the sale exactly as it was charged', function (): void {
    [$order, $draft, $promotionId] = $this->store->run(static function (): array {
        addTaxRate('Standard', 'NG', null, '7.5');
        $promotion = runningPromotion(['percent' => Decimal::of(10), 'title' => ['en' => '10% off', 'fr' => '10 % de remise']]);
        // Checkout prices in the buyer's language, so labels arrive in it too.
        app()->setLocale('fr');
        $draft = orderDraft([[optionedVariant(10000), 2], [cartVariant(5000, name: 'Desk lamp'), 1]], orderShippingMethod(), 1500, locale: 'fr', billingAddress: orderAddress('1 Billing Road'));

        return [placeOrder($draft)->load(['lines.discounts', 'lines.taxes', 'discounts', 'taxes', 'shippingLine', 'addresses', 'events']), $draft, $promotion->id];
    });
    $priced = $draft->basket->priced;

    expect($order->only(['number', 'currency', 'base_currency', 'locale', 'contact_email', 'contact_name', 'contact_phone', 'customer_note', 'tax_calculated_by', 'tax_country_code']))
        ->toBe(['number' => 1001, 'currency' => 'NGN', 'base_currency' => 'NGN', 'locale' => 'fr', 'contact_email' => 'ada@example.com', 'contact_name' => 'Ada Obi', 'contact_phone' => '+2348012345678', 'customer_note' => 'Leave it at the gate.', 'tax_calculated_by' => 'rate_table', 'tax_country_code' => 'NG'])
        ->and([$order->status->value, $order->payment_status->value, $order->fulfilment_status->value, $order->tax_mode->value, $order->rate_source->value])->toBe(['open', 'unpaid', 'unfulfilled', 'inclusive', 'base'])
        ->and($order->exchange_rate->isEqualTo(1))->toBeTrue()
        // 2 × ₦100 + ₦50, 10% off (₦25), and ₦15 shipping; prices include 7.5% VAT.
        ->and(array_map(static fn ($money): int => $money->minorAmount(), [$order->subtotal, $order->discount, $order->shipping, $order->shipping_discount, $order->tax, $order->total, $order->base_total]))
        ->toBe([25000, 2500, 1500, 0, $priced->tax()->minorAmount(), 24000, 24000])
        ->and($order->tax->isEqualTo($priced->tax()))->toBeTrue()
        ->and($order->total->isEqualTo($priced->total()))->toBeTrue();

    // Each line: names in the buyer's language, its SKU, price, discount and tax, as charged.
    $shirt = $order->lines[0];
    expect($shirt->only(['position', 'product_name', 'variant_name', 'sku', 'quantity', 'requires_shipping', 'tax_class_name']))
        ->toBe(['position' => 0, 'product_name' => 'Chemise en lin', 'variant_name' => 'Bleu / M', 'sku' => 'LIN-BLU-M', 'quantity' => 2, 'requires_shipping' => true, 'tax_class_name' => 'Standard'])
        ->and([$shirt->unit_price->minorAmount(), $shirt->subtotal->minorAmount(), $shirt->discount->minorAmount()])->toBe([10000, 20000, 2000])
        ->and($shirt->total->isEqualTo($priced->lines['line-1']->total()))->toBeTrue()
        ->and($order->lines[1]->only(['product_name', 'variant_name']))->toBe(['product_name' => 'Desk lamp', 'variant_name' => null]);

    // Each discount per promotion per line, with its label in the buyer's language; each tax per line and on shipping.
    expect($shirt->discounts->map->only(['source', 'label', 'class'])->all())->toBe([['source' => 'promotion', 'label' => '10 % de remise', 'class' => App\Tenant\Promotions\Enums\DiscountClass::Order]])
        ->and($order->discounts->sum(static fn ($discount): int => $discount->discount->minorAmount()))->toBe(2500)
        ->and($order->discounts->pluck('promotion_id')->unique()->all())->toBe([$promotionId])
        ->and($shirt->taxes->map(static fn ($tax): array => [$tax->name, $tax->rate_percent->toString(), $tax->tax->minorAmount()])->all())->toBe([['VAT', '7.5000', $priced->lines['line-1']->tax->tax->minorAmount()]])
        ->and($order->taxes->whereNull('order_line_id')->sole()->tax->isEqualTo($priced->shipping?->tax ?? throw new LogicException('Shipping is taxed.')))->toBeTrue();

    // The shipping line and the addresses, as copies.
    expect($order->shippingLine?->only(['method_name', 'carrier']))->toBe(['method_name' => 'Standard (fr)', 'carrier' => null])
        ->and([$order->shippingLine?->fulfilment, $order->shippingLine?->price->minorAmount(), $order->shippingLine?->total->minorAmount()])->toBe([ShippingFulfilment::Ship, 1500, 1500])
        ->and($order->addresses->map(static fn ($address): array => [$address->kind->value, $address->version, $address->line1])->all())->toBe([['billing', 1, '1 Billing Road'], ['shipping', 1, '5 Allen Avenue']])
        ->and($order->events->map(static fn ($event): string => $event->type->value)->all())->toBe(['placed'])
        ->and($order->payment_due_at->diffInMinutes($order->placed_at, true))->toEqualWithDelta(60, 0.1);
});

it('keeps the order as placed when products, promotions, taxes, shipping and addresses change later', function (): void {
    $snapshot = static fn (Order $order): array => [
        $order->total->minorAmount(), $order->tax->minorAmount(), $order->discount->minorAmount(),
        $order->lines->map->only(['product_name', 'sku'])->all(), $order->lines->map(static fn ($line): int => $line->unit_price->minorAmount())->all(),
        $order->discounts->map->only(['label'])->all(), $order->taxes->map(static fn ($tax): string => $tax->rate_percent->toString())->all(),
        $order->shippingLine?->method_name, $order->addresses->map->only(['line1'])->all(),
    ];

    [$before, $after] = $this->store->run(static function () use ($snapshot): array {
        addTaxRate('Standard', 'NG', null, '7.5');
        $promotion = runningPromotion(['percent' => Decimal::of(10)]);
        $variant = cartVariant(10000, name: 'Linen shirt');
        $method = orderShippingMethod();
        $order = placeOrder(orderDraft([[$variant, 1]], $method));
        $relations = ['lines', 'discounts', 'taxes', 'shippingLine', 'addresses'];
        $before = $snapshot($order->load($relations));

        $variant->product->forceFill(['name' => ['en' => 'Silk shirt']])->save();
        $variant->forceFill(['sku' => 'NEW-SKU', 'price' => App\Shared\Money\Money::ofMinor(99999, 'NGN')])->save();
        $promotion->forceFill(['title' => ['en' => 'Renamed']])->save();
        $promotion->delete();
        DB::table('tax_rates')->update(['rate' => '20']);
        $method->forceFill(['name' => ['en' => 'Renamed method']])->save();
        $variant->delete();

        return [$before, $snapshot(Order::query()->with($relations)->findOrFail($order->id))];
    });

    expect($after)->toBe($before)
        ->and($before[3][0]['product_name'])->toBe('Linen shirt');
});

it('hands the checkout\'s stock hold and promotion uses to the order, with no expiry of their own', function (): void {
    [$order, $reservations, $uses] = $this->store->run(static function (): array {
        runningPromotion(['percent' => Decimal::of(10), 'usage_limit' => 5]);
        $order = placeOrder(orderDraft([[cartVariant(10000, stock: 5), 2]], orderShippingMethod()));

        return [$order, StockReservation::query()->get(), PromotionRedemption::query()->get()];
    });

    expect($reservations->map->only(['holder_type', 'holder_id', 'quantity', 'expires_at'])->all())->toBe([['holder_type' => 'order', 'holder_id' => $order->public_id, 'quantity' => 2, 'expires_at' => null]])
        ->and($reservations->sole()->status)->toBe(ReservationStatus::Active)
        ->and($uses->map->only(['holder_type', 'holder_id', 'expires_at'])->all())->toBe([['holder_type' => 'order', 'holder_id' => $order->public_id, 'expires_at' => null]])
        ->and($uses->sole()->status)->toBe(RedemptionStatus::Reserved);
});

it('leaves a promotion use whose checkout hold had already run out, for the sweep', function (): void {
    $uses = $this->store->run(static function (): array {
        runningPromotion(['percent' => Decimal::of(10)]);
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod());
        PromotionRedemption::query()->update(['expires_at' => now()->subMinute()]);
        placeOrder($draft);

        return PromotionRedemption::query()->get(['holder_type', 'status'])->map(static fn ($use): array => [$use->holder_type, $use->status->value])->all();
    });

    expect($uses)->toBe([['checkout', 'reserved']]);
});

it('places a checkout once: placing it again, or at the same moment, returns the same order', function (): void {
    [$first, $again, $counts] = $this->store->run(static function (): array {
        $draft = orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod());
        $first = app(PlaceOrder::class)->handle($draft);
        $again = app(PlaceOrder::class)->handle($draft);

        return [$first, $again, [Order::query()->count(), DB::table('order_lines')->count(), DB::table('order_events')->count()]];
    });

    expect([$first->isNew, $again->isNew])->toBe([true, false])
        ->and($again->order->id)->toBe($first->order->id)
        ->and($counts)->toBe([1, 1, 1]);

    // A second request placing the same checkout waits for the first, then finds its order.
    $this->store->run(static function (): void {
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod(name: 'Second'));
        $otherRequest = SecondConnection::open()->holdAdvisoryLock('order-checkout:'.$draft->checkoutId);

        expect($otherRequest->blocks(static fn () => app(PlaceOrder::class)->handle($draft)))->toBeTrue();

        $otherRequest->commit();

        expect(app(PlaceOrder::class)->handle($draft)->isNew)->toBeTrue()
            ->and(app(PlaceOrder::class)->handle($draft)->isNew)->toBeFalse()
            // And the database keeps one order per checkout, whatever the code does.
            ->and(fn () => rawOrder(['checkout_id' => $draft->checkoutId]))->toThrow(QueryException::class, 'orders_checkout_id_unique');
    });
});

it('gives every order a payment deadline: the usual one, or a later one up to the maximum, never none', function (): void {
    $this->store->run(static function (): void {
        $shirt = cartVariant(10000);
        $method = orderShippingMethod();
        $later = CarbonImmutable::now()->addDays(3)->startOfSecond();

        expect(placeOrder(orderDraft([[$shirt, 1]], $method, paymentDueAt: $later))->payment_due_at->equalTo($later))->toBeTrue()
            ->and(fn () => placeOrder(orderDraft([[$shirt, 1]], $method, paymentDueAt: CarbonImmutable::now()->addDays(31))))->toThrow(InvalidOrderDraftException::class, 'max_payment_window_minutes')
            ->and(fn () => placeOrder(orderDraft([[$shirt, 1]], $method, paymentDueAt: CarbonImmutable::now()->subMinute())))->toThrow(InvalidOrderDraftException::class, 'in the future')
            // The database refuses an order without one, too.
            ->and(fn () => rawOrder(['payment_due_at' => null]))->toThrow(QueryException::class, 'payment_due_at');
    });
});

it('refuses a draft whose parts don\'t fit together', function (Closure $draftOf, string $message): void {
    expect(fn () => $this->store->run(static fn () => placeOrder($draftOf())))->toThrow(InvalidOrderDraftException::class, $message);

    expect($this->store->run(static fn (): int => Order::query()->count()))->toBe(0);
})->with([
    'lines that ship, with no shipping' => [fn () => orderDraft([[cartVariant(10000), 1]]), 'need a shipping method'],
    'shipping when nothing ships' => [function () {
        $variant = cartVariant(10000);
        $variant->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();

        return orderDraft([[$variant, 1]], orderShippingMethod());
    }, 'Nothing ships'],
    'no address to ship to' => [function () {
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod());

        return new App\Tenant\Orders\Data\OrderDraft($draft->checkoutId, $draft->basket, $draft->buyer, null, null, $draft->shipping, $draft->stockHolder, $draft->promotionHolder, 'en');
    }, 'needs their address'],
    'a carrier rate not yet quoted' => [function () {
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod());

        return new App\Tenant\Orders\Data\OrderDraft($draft->checkoutId, $draft->basket, $draft->buyer, null, $draft->shippingAddress, new ShippingOption($draft->shipping?->method ?? throw new LogicException, null, false), $draft->stockHolder, $draft->promotionHolder, 'en');
    }, 'must be quoted'],
    'shipping charged at another price' => [function () {
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod());

        return new App\Tenant\Orders\Data\OrderDraft($draft->checkoutId, $draft->basket, $draft->buyer, null, $draft->shippingAddress, new ShippingOption($draft->shipping?->method ?? throw new LogicException, App\Shared\Money\Money::ofMinor(9999, 'NGN'), true), $draft->stockHolder, $draft->promotionHolder, 'en');
    }, 'chosen method\'s price'],
    'line discounts their promotions don\'t account for' => [function () {
        runningPromotion(['percent' => Decimal::of(10)]);
        $draft = orderDraft([[cartVariant(10000), 1]], orderShippingMethod());
        $withoutPromotions = new App\Tenant\Promotions\Data\DiscountedBasket($draft->basket->priced, new App\Tenant\Promotions\Data\DiscountOutcome('NGN', [], [], App\Shared\Money\Money::zero('NGN')));

        return new App\Tenant\Orders\Data\OrderDraft($draft->checkoutId, $withoutPromotions, $draft->buyer, null, $draft->shippingAddress, $draft->shipping, $draft->stockHolder, $draft->promotionHolder, 'en');
    }, 'what its promotions took off'],
]);

it('needs no shipping, address or fulfilment when nothing ships', function (): void {
    $order = $this->store->run(static function (): Order {
        $variant = cartVariant(10000);
        $variant->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();

        return placeOrder(orderDraft([[$variant, 1]]))->load(['shippingLine', 'addresses']);
    });

    expect($order->fulfilment_status->value)->toBe('not_required')
        ->and($order->shippingLine)->toBeNull()
        ->and($order->addresses)->toBeEmpty()
        ->and($order->shipping->minorAmount())->toBe(0);
});

it('copies a pickup\'s address onto the shipping line, and needs no address from the buyer', function (): void {
    $order = $this->store->run(static fn (): Order => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(ShippingFulfilment::Pickup, 'Collect in Ikeja'), 0))->load(['shippingLine', 'addresses']));

    expect($order->shippingLine?->only(['method_name', 'pickup_address_line1', 'pickup_city', 'pickup_state_code']))->toBe(['method_name' => 'Collect in Ikeja', 'pickup_address_line1' => '1 Allen Avenue', 'pickup_city' => 'Ikeja', 'pickup_state_code' => 'LA'])
        ->and($order->addresses)->toBeEmpty();
});

it('links a guest order to the account whose verified email it was placed with, and only then', function (): void {
    [$linked, $unverified, $signedIn, $events] = $this->store->run(static function (): array {
        $verified = Customer::factory()->create(['email' => 'ada@example.com', 'email_verified_at' => now()]);
        $unverified = Customer::factory()->create(['email' => 'eve@example.com', 'email_verified_at' => null]);
        $method = orderShippingMethod();
        $shirt = cartVariant(10000);

        $linked = placeOrder(orderDraft([[$shirt, 1]], $method, buyer: OrderBuyer::guest('ADA@example.com')));
        $notLinked = placeOrder(orderDraft([[$shirt, 1]], $method, buyer: OrderBuyer::guest('eve@example.com')));
        $signedIn = placeOrder(orderDraft([[$shirt, 1]], $method, buyer: OrderBuyer::customer($unverified)));

        return [[$linked->customer_id, $verified->id], $notLinked->customer_id, [$signedIn->customer_id, $unverified->id], $linked->events()->pluck('type')->map->value->all()];
    });

    expect($linked[0])->toBe($linked[1])
        ->and($unverified)->toBeNull()
        ->and($signedIn[0])->toBe($signedIn[1])
        ->and($events)->toBe(['placed', 'linked_to_customer']);
});

it('tells first-order promotions who has ordered: by account or by the email an order was placed with, cancelled orders aside', function (): void {
    $this->store->run(static function (): void {
        $history = app(OrderHistory::class);
        $customer = Customer::factory()->create(['email' => 'sam@example.com']);
        $method = orderShippingMethod();

        expect($history->hasOrdered(null, 'ada@example.com'))->toBeFalse();

        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::customer($customer, 'sam.work@example.com')));

        expect($history->hasOrdered(null, 'ADA@example.com '))->toBeTrue()
            ->and($history->hasOrdered($customer, null))->toBeTrue()
            ->and($history->hasOrdered(Customer::factory()->create(), null))->toBeFalse()
            ->and($history->hasOrdered(null, 'sam.work@example.com'))->toBeTrue()
            ->and($history->hasOrdered(null, 'nobody@example.com'))->toBeFalse()
            ->and($history->hasOrdered(null, null))->toBeFalse();

        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff']);

        expect($history->hasOrdered(null, 'ada@example.com'))->toBeFalse();
    });
});

it('places orders in currencies with no decimals and with three, keeping the total in the base currency', function (string $currency, string $rate, int $unitPrice): void {
    $order = $this->store->run(static function () use ($currency, $rate): Order {
        offerStoreCurrency($currency, $rate);
        $variant = cartVariant(10000);
        $variant->forceFill(['requires_shipping' => false, 'weight_grams' => null])->save();

        return placeOrder(orderDraft([[$variant, 3]], currency: $currency))->load('lines');
    });

    // ₦100 at the store's rate, three of them; ₦300 back in the base currency.
    expect([$order->currency, $order->lines->sole()->unit_price->minorAmount(), $order->total->minorAmount()])->toBe([$currency, $unitPrice, $unitPrice * 3])
        ->and($order->exchange_rate->isEqualTo(Decimal::of($rate)))->toBeTrue()
        ->and([$order->base_total->currency(), $order->base_total->minorAmount()])->toBe(['NGN', 30000]);
})->with([
    'yen, no decimals' => ['JPY', '0.1', 10],
    'Kuwaiti dinar, three decimals' => ['KWD', '0.002', 200],
]);

it('announces OrderPlaced once the order is saved, never for one rolled back or placed again', function (): void {
    $announced = [];
    Event::listen(OrderPlaced::class, static function (OrderPlaced $event) use (&$announced): void {
        $announced[] = $event->order->number;
    });

    $this->store->run(static function (): void {
        $method = orderShippingMethod();
        $draft = orderDraft([[cartVariant(10000), 1]], $method);

        expect(fn () => DB::transaction(static function () use ($method): never {
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method));

            throw new RuntimeException('Checkout failed after placing.');
        }))->toThrow(RuntimeException::class, 'Checkout failed after placing.');

        placeOrder($draft);
        placeOrder($draft);
    });

    expect($announced)->toHaveCount(1);
});

it('copies a free-shipping promotion\'s discount onto the shipping', function (): void {
    $order = $this->store->run(static function (): Order {
        runningPromotion(['type' => PromotionType::FreeShipping, 'applies_to' => null, 'percent' => null, 'title' => ['en' => 'Free delivery']]);

        return placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(), 1500))->load(['discounts', 'shippingLine']);
    });

    expect($order->discounts->map(static fn ($discount): array => [$discount->order_line_id, $discount->class->value, $discount->label, $discount->discount->minorAmount()])->all())->toBe([[null, 'shipping', 'Free delivery', 1500]])
        ->and([$order->shipping->minorAmount(), $order->shipping_discount->minorAmount(), $order->total->minorAmount()])->toBe([1500, 1500, 10000])
        ->and([$order->shippingLine?->price->minorAmount(), $order->shippingLine?->discount->minorAmount(), $order->shippingLine?->total->minorAmount()])->toBe([1500, 1500, 0]);
});
