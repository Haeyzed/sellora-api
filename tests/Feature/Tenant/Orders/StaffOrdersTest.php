<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Customers\Data\AddressSnapshot;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Actions\CorrectShippingAddress;
use App\Tenant\Orders\Data\OrderBuyer;
use App\Tenant\Orders\Exceptions\OrderAddressCannotBeCorrectedException;
use App\Tenant\Orders\Exceptions\OrderAddressRegionChangedException;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Promotions\Contracts\OrderHistory;
use App\Tenant\Shipping\Enums\ShippingFulfilment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 14d: staff work with orders. They list them newest first (by
 * cursor), search by number or whole email, and narrow by status, date,
 * customer and more; see each with its history; cancel; tag; add notes; and
 * correct the contact details, or the shipping address before anything
 * ships and within the same country and state. Corrections are new
 * versions: what the order was placed with never changes, and a corrected
 * email never links the order to an account or changes first-order checks.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists orders newest first, a page at a time, with the same number of queries for 1 order or 20', function (): void {
    $this->store->run(static function (): void {
        $method = orderShippingMethod();
        placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('first@example.com')));
    });

    $page = staffOrderRequest('GET', '/orders?per_page=1')->assertOk();
    expect($page->json('data.0'))->toMatchArray(['number' => 1001, 'status' => 'open', 'payment_status' => 'unpaid', 'fulfilment_status' => 'unfulfilled', 'contact' => ['email' => 'first@example.com', 'name' => null]])
        ->and($page->json('data.0.total.amount'))->toBe(11500);

    $queries = [];
    foreach ([1, 20] as $count) {
        $this->store->run(static function () use ($count): void {
            $method = orderShippingMethod(name: "Method {$count}");

            foreach (range(1, $count) as $ignored) {
                $order = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
                DB::table('order_tags')->insert(['order_id' => $order->id, 'tag' => 'bulk']);
            }
        });

        $this->store->run(static function () use (&$queries, $count): void {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $orders = Order::query()->with(App\Tenant\Orders\Http\Controllers\StaffOrderController::SUMMARY)->orderByDesc('placed_at')->orderByDesc('id')->cursorPaginate(25);
            App\Tenant\Orders\Http\Resources\OrderSummaryResource::collection($orders)->toArray(request());
            $queries[$count] = count(DB::getQueryLog());
            DB::disableQueryLog();
        });
    }

    expect($queries[20])->toBe($queries[1]);

    $first = staffOrderRequest('GET', '/orders?per_page=10')->assertOk();
    expect($first->json('data'))->toHaveCount(10)
        ->and($first->json('data.0.number'))->toBe(1022)
        ->and($first->json('meta.next_cursor'))->toBeString();
    $next = staffOrderRequest('GET', '/orders?per_page=10&cursor='.$first->json('meta.next_cursor'))->assertOk();
    expect($next->json('data.0.number'))->toBe(1012);
});

it('searches by order number or whole email, and narrows by status, date, customer, currency, method, promotion and tag', function (): void {
    [$customer, $method, $promotion] = $this->store->run(static function (): array {
        $customer = Customer::factory()->create(['email' => 'sam@example.com', 'email_verified_at' => now()]);
        $method = orderShippingMethod();
        $pickup = orderShippingMethod(ShippingFulfilment::Pickup, 'Collect');
        offerStoreCurrency('USD', '0.001');

        $first = placeOrder(orderDraft([[cartVariant(10000), 1]], $method, buyer: OrderBuyer::guest('ada@example.com')));
        DB::table('order_tags')->insert(['order_id' => $first->id, 'tag' => 'wholesale']);
        markPaid(placeOrder(orderDraft([[cartVariant(10000), 1]], $pickup, 0, buyer: OrderBuyer::customer($customer))));
        $promotion = runningPromotion(['percent' => Decimal::of(10)]);
        $usd = placeOrder(orderDraft([[cartVariant(10000), 1]], $method, 2, currency: 'USD', buyer: OrderBuyer::guest('bo@example.com')));
        DB::table('order_tags')->insert(['order_id' => $usd->id, 'tag' => 'gift']);

        return [$customer->public_id, $pickup->public_id, $promotion->public_id];
    });
    $numbers = static fn (string $query): array => staffOrderRequest('GET', '/orders?'.$query)->assertOk()->json('data.*.number');

    expect($numbers('search=1002'))->toBe([1002])
        ->and($numbers('search='.urlencode('#1003')))->toBe([1003])
        ->and($numbers('search=ADA@example.com'))->toBe([1001])
        ->and($numbers('search=ada'))->toBe([])
        ->and($numbers('payment_status=paid'))->toBe([1002])
        ->and($numbers('status=open&payment_status=unpaid'))->toBe([1003, 1001])
        ->and($numbers('fulfilment_status=unfulfilled'))->toBe([1003, 1002, 1001])
        ->and($numbers("customer={$customer}"))->toBe([1002])
        ->and($numbers('currency=usd'))->toBe([1003])
        ->and($numbers("shipping_method={$method}"))->toBe([1002])
        ->and($numbers("promotion={$promotion}"))->toBe([1003])
        ->and($numbers('tag=GIFT'))->toBe([1003])
        ->and($numbers('refund_due=1'))->toBe([])
        ->and($numbers('placed_from='.urlencode(now()->addMinute()->toIso8601String())))->toBe([])
        ->and($numbers('placed_to='.urlencode(now()->addMinute()->toIso8601String())))->toBe([1003, 1002, 1001]);

    staffOrderRequest('GET', '/orders?status=shipped')->assertJsonValidationErrors('status');
    expect(staffOrderRequest('GET', "/customers/{$customer}/orders")->assertOk()->json('data.*.number'))->toBe([1002]);
});

it('shows an order with its lines, discounts, taxes, shipping, addresses and history, found only by its ID', function (): void {
    $order = $this->store->run(static function (): Order {
        addTaxRate('Standard', 'NG', null, '7.5');
        runningPromotion(['percent' => Decimal::of(10)]);

        return placeOrder(orderDraft([[optionedVariant(10000), 2]], orderShippingMethod(), billingAddress: orderAddress('1 Billing Road')));
    });

    $shown = staffOrderRequest('GET', "/orders/{$order->public_id}")->assertOk();
    expect($shown->json('data'))->toMatchArray(['id' => $order->public_id, 'number' => 1001, 'currency' => 'NGN', 'locale' => 'en', 'customer' => null, 'customer_note' => 'Leave it at the gate.', 'tags' => []])
        ->and($shown->json('data.contact'))->toBe(['email' => 'ada@example.com', 'name' => 'Ada Obi', 'phone' => '+2348012345678', 'is_corrected' => false])
        ->and($shown->json('data.lines.0'))->toMatchArray(['quantity' => 2, 'variant' => ['id' => $shown->json('data.lines.0.variant.id'), 'name' => 'Blue / M', 'sku' => 'LIN-BLU-M']])
        ->and($shown->json('data.lines.0.discounts.0.label'))->toBe('10% off')
        ->and($shown->json('data.lines.0.taxes.0'))->toMatchArray(['name' => 'VAT', 'rate_percent' => '7.5000'])
        ->and($shown->json('data.shipping'))->toMatchArray(['fulfilment' => 'ship', 'carrier' => null])
        ->and($shown->json('data.shipping.taxes.0.name'))->toBe('VAT')
        ->and($shown->json('data.shipping_address'))->toMatchArray(['line1' => '5 Allen Avenue', 'version' => 1])
        ->and($shown->json('data.billing_address.line1'))->toBe('1 Billing Road')
        ->and($shown->json('data.exchange_rate'))->toMatchArray(['base_currency' => 'NGN', 'source' => 'base'])
        ->and(staffOrderRequest('GET', "/orders/{$order->public_id}/events")->assertOk()->json('data.*.type'))->toBe(['placed']);

    // The number is for people: it never finds an order.
    staffOrderRequest('GET', '/orders/1001')->assertNotFound();
});

it('lets staff with orders.view only look, and nobody else even that', function (): void {
    $order = $this->store->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()))->public_id);
    $nobody = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());

    staffOrderRequest('GET', '/orders', as: $this->viewer)->assertOk();
    staffOrderRequest('GET', "/orders/{$order}", as: $this->viewer)->assertOk();
    staffOrderRequest('GET', "/orders/{$order}/events", as: $this->viewer)->assertOk();
    staffOrderRequest('GET', "/orders/{$order}/notes", as: $this->viewer)->assertOk();

    foreach ([['POST', "/orders/{$order}/cancel", []], ['PUT', "/orders/{$order}/tags", ['tags' => ['x']]], ['PUT', "/orders/{$order}/contact", ['email' => 'x@example.com']], ['PUT', "/orders/{$order}/shipping-address", orderAddressPayload()], ['POST', "/orders/{$order}/notes", ['body' => 'x']]] as [$method, $path, $data]) {
        staffOrderRequest($method, $path, $data, as: $this->viewer)->assertForbidden();
    }

    staffOrderRequest('GET', '/orders', as: $nobody)->assertForbidden();
    staffOrderRequest('GET', "/orders/{$order}", as: $nobody)->assertForbidden();
});

it('cancels an open order for staff, logging who did it, and refuses one that can\'t be', function (): void {
    [$open, $done] = $this->store->run(static function (): array {
        $method = orderShippingMethod();
        $done = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        DB::table('orders')->where('id', $done->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'customer']);

        return [placeOrder(orderDraft([[cartVariant(10000), 1]], $method))->public_id, $done->public_id];
    });

    staffOrderRequest('POST', "/orders/{$open}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancellation_reason', 'staff')->assertJsonPath('data.refund_due', false);
    staffOrderRequest('POST', "/orders/{$done}/cancel")->assertConflict()->assertJsonPath('code', 'order_cannot_be_cancelled');

    expect($this->store->run(static fn (): array => Activity::query()->inLog('orders')->get()->map->only(['event', 'description'])->all()))->toBe([['event' => 'order_cancelled', 'description' => 'Cancelled order #1002']]);
});

it('keeps contact corrections as new versions: the details placed with never change, and never link the order or change first-order checks', function (): void {
    $order = $this->store->run(static function (): string {
        Customer::factory()->create(['email' => 'ada.new@example.com', 'email_verified_at' => now()]);

        return placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()))->public_id;
    });

    $corrected = staffOrderRequest('PUT', "/orders/{$order}/contact", ['email' => 'Ada.New@example.com', 'name' => 'Ada Obi', 'phone' => '+2348099999999'])->assertOk();
    expect($corrected->json('data.contact'))->toBe(['email' => 'ada.new@example.com', 'name' => 'Ada Obi', 'phone' => '+2348099999999', 'is_corrected' => true])
        ->and($corrected->json('data.customer'))->toBeNull()
        // The corrected email finds the order; the placed one still does too.
        ->and(staffOrderRequest('GET', '/orders?search=ada.new@example.com')->json('data.*.id'))->toBe([$order])
        ->and(staffOrderRequest('GET', '/orders?search=ada@example.com')->json('data.*.id'))->toBe([$order]);

    $this->store->run(static function () use ($order): void {
        $placed = Order::query()->where('public_id', $order)->sole();
        $history = app(OrderHistory::class);

        expect($placed->only(['contact_email', 'contact_phone', 'customer_id']))->toBe(['contact_email' => 'ada@example.com', 'contact_phone' => '+2348012345678', 'customer_id' => null])
            ->and($history->hasOrdered(null, 'ada.new@example.com'))->toBeFalse()
            ->and($history->hasOrdered(null, 'ada@example.com'))->toBeTrue()
            ->and($placed->events()->get()->last()?->data)->toBe(['fields' => ['email', 'phone']])
            ->and(Activity::query()->inLog('orders')->sole()->properties->all())->toBe(['fields' => ['email', 'phone']])
            ->and(fn () => DB::table('order_contact_corrections')->update(['email' => 'eve@example.com']))->toThrow(QueryException::class, 'change only through erasure')
            ->and(fn () => DB::table('order_contact_corrections')->delete())->toThrow(QueryException::class, 'change only through erasure')
            ->and(fn () => DB::statement('truncate order_contact_corrections'))->toThrow(QueryException::class, 'financial records')
            ->and(app(PersonalDataErasure::class)->run(static fn (): int => DB::table('order_contact_corrections')->update(['email' => null, 'name' => null, 'phone' => null])))->toBe(1);
    });
});

it('corrects a shipping address as a new version, only before anything ships and within the same country and state', function (): void {
    [$open, $shipped, $pickup] = $this->store->run(static function (): array {
        $method = orderShippingMethod();
        $shipped = placeOrder(orderDraft([[cartVariant(10000), 1]], $method));
        DB::table('orders')->where('id', $shipped->id)->update(['fulfilment_status' => 'partially_fulfilled']);

        return [
            placeOrder(orderDraft([[cartVariant(10000), 1]], $method))->public_id,
            $shipped->public_id,
            placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod(ShippingFulfilment::Pickup, 'Collect'), 0))->public_id,
        ];
    });

    staffOrderRequest('PUT', "/orders/{$open}/shipping-address", orderAddressPayload(['line1' => '9 Awolowo Road']))->assertOk()
        ->assertJsonPath('data.shipping_address.line1', '9 Awolowo Road')->assertJsonPath('data.shipping_address.version', 2);
    staffOrderRequest('PUT', "/orders/{$open}/shipping-address", orderAddressPayload(['state' => 'OG']))->assertUnprocessable()->assertJsonPath('code', 'order_address_region_changed')->assertJsonValidationErrors(['country', 'state']);
    staffOrderRequest('PUT', "/orders/{$open}/shipping-address", orderAddressPayload(['country' => 'DE', 'state' => null, 'city' => 'Berlin', 'postal_code' => '10115', 'phone' => null]))->assertUnprocessable()->assertJsonPath('code', 'order_address_region_changed');
    // Another country is refused even where its states use the same code.
    expect(fn () => $this->store->run(fn (): Order => app(CorrectShippingAddress::class)->handle($this->owner, Order::query()->where('public_id', $open)->sole(), new AddressSnapshot('Ada Obi', null, '9 Awolowo Road', null, 'Ikeja', 'LA', '100271', 'GH', null))))
        ->toThrow(OrderAddressRegionChangedException::class);
    staffOrderRequest('PUT', "/orders/{$shipped}/shipping-address", orderAddressPayload())->assertConflict()->assertJsonPath('code', 'order_address_cannot_be_corrected');
    staffOrderRequest('PUT', "/orders/{$pickup}/shipping-address", orderAddressPayload())->assertConflict()->assertJsonPath('code', 'order_address_cannot_be_corrected');

    staffOrderRequest('POST', "/orders/{$open}/cancel")->assertOk();
    staffOrderRequest('PUT', "/orders/{$open}/shipping-address", orderAddressPayload())->assertConflict();

    expect($this->store->run(static fn (): array => DB::table('order_addresses')->where('order_id', Order::query()->where('public_id', $open)->value('id'))->orderBy('version')->pluck('line1')->all()))->toBe(['5 Allen Avenue', '9 Awolowo Road']);
});

it('makes an address correction wait for a change to the order at the same moment, then decide on what was saved', function (): void {
    $this->store->run(function (): void {
        $order = placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()));
        $correct = fn (): Order => app(CorrectShippingAddress::class)->handle($this->owner, $order, orderAddress('9 Awolowo Road'));

        // Another member of staff is cancelling it: they hold its row and have cancelled it, but not yet committed.
        $other = SecondConnection::open()->holdRowLock($order);
        $other->run(static fn () => DB::connection(SecondConnection::NAME)->table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'staff']));

        expect($other->blockedStatement($correct))->toContain('for update');
        $other->commit();

        expect($correct)->toThrow(OrderAddressCannotBeCorrectedException::class)
            ->and(DB::table('order_addresses')->where('order_id', $order->id)->count())->toBe(1);
    });
});

it('replaces an order\'s tags, in lower case and once each, and records the change', function (): void {
    $order = $this->store->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()))->public_id);

    staffOrderRequest('PUT', "/orders/{$order}/tags", ['tags' => ['Gift', 'gift', 'VIP']])->assertOk()->assertJsonPath('data.tags', ['gift', 'vip']);
    staffOrderRequest('PUT', "/orders/{$order}/tags", ['tags' => ['vip', 'wholesale']])->assertOk()->assertJsonPath('data.tags', ['vip', 'wholesale']);
    staffOrderRequest('PUT', "/orders/{$order}/tags", ['tags' => ['  ']])->assertJsonValidationErrors('tags.0');
    staffOrderRequest('PUT', "/orders/{$order}/tags", ['tags' => []])->assertOk()->assertJsonPath('data.tags', []);

    expect(staffOrderRequest('GET', "/orders/{$order}/events")->json('data.*.data'))->toBe([
        ['number' => 1001],
        ['added' => ['gift', 'vip'], 'removed' => []],
        ['added' => ['wholesale'], 'removed' => ['gift']],
        ['added' => [], 'removed' => ['vip', 'wholesale']],
    ]);
});

it('keeps staff notes on an order, never another order\'s', function (): void {
    [$order, $other] = $this->store->run(static function (): array {
        $method = orderShippingMethod();

        return [placeOrder(orderDraft([[cartVariant(10000), 1]], $method))->public_id, placeOrder(orderDraft([[cartVariant(10000), 1]], $method))->public_id];
    });

    $note = staffOrderRequest('POST', "/orders/{$order}/notes", ['body' => '  Call before delivering.  '])->assertCreated()->assertJsonPath('data.body', 'Call before delivering.')->json('data.id');
    expect(staffOrderRequest('GET', "/orders/{$order}/notes")->json('data.*.id'))->toBe([$note])
        ->and(staffOrderRequest('GET', "/orders/{$other}/notes")->json('data'))->toBe([]);

    staffOrderRequest('DELETE', "/orders/{$other}/notes/{$note}")->assertNotFound();
    staffOrderRequest('DELETE', "/orders/{$order}/notes/{$note}", as: $this->viewer)->assertForbidden();
    staffOrderRequest('DELETE', "/orders/{$order}/notes/{$note}")->assertNoContent();
    expect(staffOrderRequest('GET', "/orders/{$order}/notes")->json('data'))->toBe([]);
});

it('never shows or changes another store\'s orders', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    $theirs = $other->run(static fn (): string => placeOrder(orderDraft([[cartVariant(10000), 1]], orderShippingMethod()))->public_id);

    staffOrderRequest('GET', "/orders/{$theirs}")->assertNotFound();
    staffOrderRequest('POST', "/orders/{$theirs}/cancel")->assertNotFound();
    expect(staffOrderRequest('GET', '/orders')->json('data'))->toBe([])
        ->and($other->run(static fn (): string => Order::query()->sole()->status->value))->toBe('open');
});
