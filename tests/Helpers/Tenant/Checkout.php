<?php

declare(strict_types=1);

use App\Tenant\Cart\CartToken;
use App\Tenant\Cart\Models\Cart;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\CheckoutExtensions;
use App\Tenant\Checkout\Models\Checkout;
use App\Tenant\Checkout\Models\CheckoutPaymentHold;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderEvent;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Checkout\FakeClashingPaymentMethod;
use Tests\Fixtures\Checkout\FakeGiftCardPaymentMethod;
use Tests\Fixtures\Checkout\FakeWalletPaymentMethod;

/*
|--------------------------------------------------------------------------
| For the tests in tests/Feature/Tenant/Checkout
|--------------------------------------------------------------------------
*/

/**
 * An open checkout made straight in the current store, on a cart of its own (the customer's when one is given, else a guest's), with the fields given.
 *
 * @param  array<string, mixed>  $fields
 */
function rawCheckout(array $fields = []): Checkout
{
    $customerId = $fields['customer_id'] ?? null;
    $cart = new Cart;
    $cart->forceFill([
        'customer_id' => $customerId,
        'token_hash' => $customerId === null ? CartToken::hash(Str::random(64)) : null,
        'last_activity_at' => CarbonImmutable::now(),
    ])->save();

    $checkout = new Checkout;
    $checkout->forceFill([
        'cart_id' => $cart->id,
        'locale' => 'en',
        'last_activity_at' => CarbonImmutable::now(),
        ...$fields,
    ])->save();

    return $checkout;
}

/**
 * Sends a request to a store's checkout API, under /api/v1/checkout: as a guest with a cart token, as a signed-in customer, or with neither.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string>  $headers
 */
function checkoutRequest(string $method, string $path = '', array $data = [], ?string $token = null, ?Customer $as = null, string $subdomain = 'cart-store', string $ip = '203.0.113.10', array $headers = []): TestResponse
{
    forgetSignIns();
    // Other helpers can leave a staff bearer token behind.
    test()->flushHeaders();
    $request = test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders($headers);

    if ($token !== null) {
        $request = $request->withHeader(CartToken::HEADER, $token);
    }

    if ($as !== null) {
        $request = $request->withToken(customerTokenFor($as, $subdomain));
    }

    $response = $request->json($method, storeUrl($subdomain, '/api/v1/checkout'.$path), $data);
    test()->flushHeaders();
    test()->withServerVariables([]);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * The codes of the issues a checkout response lists.
 *
 * @return list<string>
 */
function checkoutIssues(TestResponse $response): array
{
    return array_column($response->json('data.issues'), 'code');
}

/**
 * A guest cart in the cart store holding the shipped lines given, as [price, quantity], and its token.
 *
 * @param  list<array{int, int}>  $lines
 */
function checkoutCart(array $lines = [[10000, 1]]): string
{
    return shippingCart(array_map(static fn (array $line): array => [$line[0], $line[1], true], $lines));
}

/*
|--------------------------------------------------------------------------
| For CheckoutPlacementTest.php and CheckoutContentionTest.php
|--------------------------------------------------------------------------
*/

/**
 * Starts checking out the cart and fills in everything an order needs: a guest's email, the test's standard shipping method to a Lagos address, and a bank transfer (turned on by the test), with the changes given on top. Returns its ID and quote version.
 *
 * @param  array<string, mixed>  $changes
 * @return array{id: string, version: int}
 */
function readyCheckout(?string $token = null, ?Customer $as = null, array $changes = []): array
{
    checkoutRequest('POST', token: $token, as: $as)->assertCreated();
    $ready = checkoutRequest('PATCH', '', [
        ...($as === null ? ['contact' => ['email' => 'ada@example.com']] : []),
        'shipping_method' => test()->standard,
        'shipping_address' => lagosAddress(),
        'payment_method' => 'bank_transfer',
        ...$changes,
    ], token: $token, as: $as)->assertOk();

    expect(checkoutIssues($ready))->toBe([]);

    return ['id' => $ready->json('data.id'), 'version' => $ready->json('data.version')];
}

/**
 * Places the order for a checkout, with an Idempotency-Key (unless null).
 */
function placeCheckout(string $checkoutId, int $version, ?string $token = null, ?Customer $as = null, ?string $key = 'place-order-0001', ?string $returnUrl = null): TestResponse
{
    return checkoutRequest('POST', '/orders', array_filter(['checkout' => $checkoutId, 'version' => $version, 'return_url' => $returnUrl], static fn (mixed $value): bool => $value !== null), token: $token, as: $as, headers: $key === null ? [] : ['Idempotency-Key' => $key]);
}

/*
|--------------------------------------------------------------------------
| For CheckoutPaymentRefundsTest.php
|--------------------------------------------------------------------------
*/

/**
 * A guest's order paid in full with the fake gift card, placed through checkout in the cart store.
 */
function giftCardPaidOrder(): Order
{
    FakeGiftCardPaymentMethod::$balance = 10_000_000;
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY], 'payment_method' => null]);
    placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()->assertJsonPath('data.order.payment_status', 'paid');

    return test()->store->run(static fn (): Order => Order::query()->sole());
}

/**
 * A module's service provider registering the clashing fake payment method, as a module would at boot.
 */
function clashingModuleProvider(): ServiceProvider
{
    return new class(app()) extends ServiceProvider
    {
        public function boot(CheckoutExtensions $checkoutExtensions): void
        {
            $checkoutExtensions->addPaymentMethod(FakeClashingPaymentMethod::class, 'clashing_module');
        }
    };
}

/**
 * A guest's order placed through checkout in the cart store, paid by two modules (the fake gift card's ₦50, then the fake wallet's ₦30) and by core's method given for the rest, not yet paid. Both modules must be registered first.
 */
function twoModulesOrder(string $paymentMethod = 'bank_transfer'): Order
{
    FakeGiftCardPaymentMethod::$balance = 5000;
    FakeWalletPaymentMethod::$balance = 3000;
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => [FakeGiftCardPaymentMethod::KEY, FakeWalletPaymentMethod::KEY], 'payment_method' => $paymentMethod]);
    placeCheckout($checkout['id'], $checkout['version'], token: $token, returnUrl: 'https://shop.example.com/thanks')->assertCreated();

    return test()->store->run(static fn (): Order => Order::query()->sole());
}

/**
 * The fake gateway confirming the test store's only payment in full, and the buyer coming back to check it.
 */
function payAtFakeGateway(): void
{
    $payment = test()->store->run(static fn (): Payment => Payment::query()->sole());
    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount);
    test()->getJson(storeUrl('cart-store', "/api/v1/checkout/payments/{$payment->reference}"))->assertOk()->assertJsonPath('data.status', 'succeeded');
}

/**
 * Each payment hold of the test store's only order, by method: its status and what it covers.
 *
 * @return array<string, array{string, int}>
 */
function paymentHolds(): array
{
    return test()->store->run(static fn (): array => CheckoutPaymentHold::query()->orderBy('id')->get()
        ->mapWithKeys(static fn (CheckoutPaymentHold $hold): array => [$hold->method => [$hold->status->value, $hold->amount->minorAmount()]])->all());
}

/**
 * Where settling the test store's hold for a payment method stands: its status, why settling it last failed, how many tries in a row failed, and whether staff were alerted and it recovered.
 *
 * @return array{status: string, failure: string|null, failures: int, alerted: bool, recovered: bool}
 */
function holdSettling(string $method): array
{
    return test()->store->run(static function () use ($method): array {
        $hold = CheckoutPaymentHold::query()->where('method', $method)->sole();

        return [
            'status' => $hold->status->value,
            'failure' => $hold->settle_failure,
            'failures' => $hold->settle_failures,
            'alerted' => $hold->settle_alerted_at !== null,
            'recovered' => $hold->settle_recovered_at !== null,
        ];
    });
}

/**
 * Staff recording the bank transfer for the rest of the test store's only order: the order is then paid.
 */
function receiveTheRest(): void
{
    $payment = test()->store->run(static fn (): Payment => Payment::query()->sole());
    recordReceived($payment, $payment->amount->minorAmount());
}

/**
 * The test store's only order and its payment holds as they stand, for comparing.
 *
 * @return array{status: string, payment_status: string, refund_due: bool, holds: array<string, array{status: string, refund_failure: string|null}>}
 */
function refundState(): array
{
    return test()->store->run(static function (): array {
        $order = Order::query()->sole();

        return [
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'refund_due' => $order->refund_due,
            'holds' => CheckoutPaymentHold::query()->orderBy('id')->get()->mapWithKeys(static fn (CheckoutPaymentHold $hold): array => [$hold->method => ['status' => $hold->status->value, 'refund_failure' => $hold->refund_failure]])->all(),
        ];
    });
}

/**
 * The test's owner cancelling the test store's only order, or the one given.
 */
function cancelAsStaff(?Order $order = null): void
{
    test()->store->run(static fn () => app(CancelOrder::class)->byStaff(test()->owner, $order?->refresh() ?? Order::query()->sole()));
}

/**
 * A guest's order in the cart store paid in full at placement by modules alone (the fake gift card for everything, or the fake wallet's ₦30 then the gift card for the rest), whose gift card can't be reached from then on: its commit failed, so its hold is still held, and releasing it fails too until the test says otherwise. The fake gift card (and the wallet) must be registered first.
 */
function modulesOnlyOrderWhoseCommitFailed(bool $withWallet = false): Order
{
    FakeGiftCardPaymentMethod::$balance = 10_000_000;
    FakeWalletPaymentMethod::$balance = 3000;
    FakeGiftCardPaymentMethod::$settleFailure = new RuntimeException('The gift card service is down.');
    $token = checkoutCart();
    $checkout = readyCheckout($token, changes: ['module_payment_methods' => $withWallet ? [FakeWalletPaymentMethod::KEY, FakeGiftCardPaymentMethod::KEY] : [FakeGiftCardPaymentMethod::KEY], 'payment_method' => null]);
    $orderId = placeCheckout($checkout['id'], $checkout['version'], token: $token)->assertCreated()->assertJsonPath('data.order.payment_status', 'paid')->json('data.order.id');

    return test()->store->run(static fn (): Order => Order::query()->where('public_id', $orderId)->sole());
}

/**
 * An order as it stands: its status, payment status, paid date, whether it owes a refund, and each hold's status by method.
 *
 * @return array{status: string, payment_status: string, paid: bool, refund_due: bool, holds: array<string, string>}
 */
function orderAfterHolds(Order $order): array
{
    return test()->store->run(static function () use ($order): array {
        $order->refresh();

        return [
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'paid' => $order->paid_at !== null,
            'refund_due' => $order->refund_due,
            'holds' => CheckoutPaymentHold::query()->where('order_id', $order->id)->orderBy('id')->get()->mapWithKeys(static fn (CheckoutPaymentHold $hold): array => [$hold->method => $hold->status->value])->all(),
        ];
    });
}

/**
 * Where recording an order's payments after its holds settled stands, by each hold's method: why it last failed and how many tries in a row failed.
 *
 * @return array<string, array{failure: string|null, failures: int}>
 */
function orderUpdateAfterHolds(Order $order): array
{
    return test()->store->run(static fn (): array => CheckoutPaymentHold::query()->where('order_id', $order->id)->orderBy('id')->get()
        ->mapWithKeys(static fn (CheckoutPaymentHold $hold): array => [$hold->method => ['failure' => $hold->order_update_failure, 'failures' => $hold->order_update_failures]])->all());
}

/**
 * An order's invoice and credit notes, for comparing: their numbers, references, totals and tax, and each line's kind, quantity, total and tax.
 *
 * @return array{invoice: array<string, mixed>|null, credit_notes: list<array<string, mixed>>}
 */
function orderTaxDocuments(Order $order): array
{
    return test()->store->run(static function () use ($order): array {
        $lines = static fn ($lines): array => $lines->sortBy('position')->map(static fn ($line): array => [$line->kind->value, $line->quantity, $line->total->minorAmount(), $line->tax->minorAmount()])->values()->all();
        $invoice = Invoice::query()->with('lines')->where('order_id', $order->id)->first();

        return [
            'invoice' => $invoice === null ? null : ['number' => $invoice->number, 'total' => $invoice->total->minorAmount(), 'tax' => $invoice->tax->minorAmount(), 'lines' => $lines($invoice->lines)],
            'credit_notes' => CreditNote::query()->with('lines')->where('order_id', $order->id)->orderBy('id')->get()->map(static fn (CreditNote $creditNote): array => [
                'number' => $creditNote->number,
                'sequence_number' => $creditNote->sequence_number,
                'reference' => $creditNote->reference,
                'refund' => $creditNote->refund_reference,
                'total' => $creditNote->total->minorAmount(),
                'tax' => $creditNote->tax->minorAmount(),
                'lines' => $lines($creditNote->lines),
            ])->all(),
        ];
    });
}

/**
 * An order's history, as "type" or "type:from>to" for a change of status, oldest first.
 *
 * @return list<string>
 */
function orderHistory(Order $order): array
{
    return test()->store->run(static fn (): array => $order->events()->orderBy('id')->get()
        ->map(static fn (OrderEvent $event): string => $event->type->value.(isset($event->data['from'], $event->data['to']) && $event->type === OrderEventType::PaymentStatusChanged ? ":{$event->data['from']}>{$event->data['to']}" : ''))->all());
}

/*
|--------------------------------------------------------------------------
| For CheckoutContentionTest.php
|--------------------------------------------------------------------------
*/

/**
 * Two guests' checkouts, ready to place, for one of each of two variants: the first guest's cart lists them in the order given, the second's the other way round.
 *
 * @return list<array{token: string, checkout: string, version: int, key: string}>
 */
function oppositeOrderCheckouts(string $firstVariant, string $secondVariant): array
{
    $checkouts = [];

    foreach ([['ada@example.com', [$firstVariant, $secondVariant]], ['grace@example.com', [$secondVariant, $firstVariant]]] as $index => [$email, $variants]) {
        $token = newGuestCart();

        foreach ($variants as $variant) {
            cartRequest('POST', '/lines', ['variant' => $variant, 'quantity' => 1], token: $token)->assertOk();
        }

        $ready = readyCheckout($token, changes: ['contact' => ['email' => $email]]);
        $checkouts[] = ['token' => $token, 'checkout' => $ready['id'], 'version' => $ready['version'], 'key' => "opposite-order-000{$index}"];
    }

    return $checkouts;
}

/**
 * The stock reserved in the test store for each variant given, by public ID.
 *
 * @param  list<string>  $variants
 * @return array<string, int>
 */
function reservedStock(array $variants): array
{
    return test()->store->run(static fn (): array => collect($variants)->mapWithKeys(static fn (string $variant): array => [
        $variant => (int) StockReservation::query()->where('product_variant_id', ProductVariant::query()->where('public_id', $variant)->value('id'))->sum('quantity'),
    ])->all());
}

/**
 * Places orders for several guest checkouts at the same moment, each from a PHP process of its own (as separate requests would be), and returns each response's status and body, in the order given.
 *
 * @param  list<array{token: string, checkout: string, version: int, key: string}>  $placements
 * @return list<array{status: int, body: array<string, mixed>}>
 */
function placeAtOnce(array $placements, string $subdomain = 'cart-store'): array
{
    return requestsAtOnce(array_map(static fn (array $placement): array => [
        'url' => storeUrl($subdomain, '/api/v1/checkout/orders'),
        'headers' => [CartToken::HEADER => $placement['token'], 'Idempotency-Key' => $placement['key']],
        'body' => ['checkout' => $placement['checkout'], 'version' => $placement['version']],
    ], $placements));
}
