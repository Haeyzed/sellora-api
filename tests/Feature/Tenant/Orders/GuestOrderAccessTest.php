<?php

declare(strict_types=1);

use App\Shared\Idempotency\IdempotencyKey;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\Payments\FakeGatewayScript;
use Tests\Fixtures\Payments\FakePaymentGateway;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 16.5: a guest reaches their order with the access token given when it
 * was placed, in the X-Order-Token header only, until it expires: to see it,
 * pay for it again, get its invoice and credit notes, and cancel it while it
 * is unpaid. Without the order's own token it is not found; a token in the
 * URL is never accepted.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00', 'UTC'));
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner] = $this->store->run(static fn (): array => orderStaff());
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('shows a guest their order with its token, in the header only, until it expires', function (): void {
    [$order, $other] = $this->store->run(static fn (): array => [unpaidOrder(), unpaidOrder()]);
    $token = guestOrderToken($order);
    $otherToken = guestOrderToken($other);

    expect(guestOrderRequest('GET', $order->public_id, $token)->assertOk()->json('data'))->toMatchArray(['id' => $order->public_id, 'number' => $order->number]);

    // No token, a wrong one, a malformed one, or another order's: not found, never forbidden.
    guestOrderRequest('GET', $order->public_id, null)->assertNotFound();
    guestOrderRequest('GET', $order->public_id, Str::random(64))->assertNotFound();
    guestOrderRequest('GET', $order->public_id, 'not-a-token')->assertNotFound();
    guestOrderRequest('GET', $order->public_id, $otherToken)->assertNotFound();
    guestOrderRequest('GET', $other->public_id, $token)->assertNotFound();

    // In the URL it is never looked at: URLs end up in logs and browser history.
    foreach (['token', 'order_token', 'X-Order-Token'] as $name) {
        guestOrderRequest('GET', $order->public_id.'?'.http_build_query([$name => $token]), null)->assertNotFound();
    }

    // Until it expires, 90 days on.
    $this->travel(90)->days();
    $this->travel(-1)->minutes();
    guestOrderRequest('GET', $order->public_id, $token)->assertOk();
    $this->travel(2)->minutes();
    guestOrderRequest('GET', $order->public_id, $token)->assertNotFound();
});

it('lets a guest cancel their order only before paying', function (): void {
    [$unpaid, $paid] = $this->store->run(static fn (): array => [unpaidOrder(), markPaid(unpaidOrder())]);
    $unpaidToken = guestOrderToken($unpaid);
    $paidToken = guestOrderToken($paid);

    guestOrderRequest('POST', "{$unpaid->public_id}/cancel", null)->assertNotFound();
    guestOrderRequest('POST', "{$unpaid->public_id}/cancel", $paidToken)->assertNotFound();
    guestOrderRequest('POST', "{$unpaid->public_id}/cancel", $unpaidToken)->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($this->store->run(static fn (): array => Order::query()->whereKey($unpaid->id)->sole()->only(['cancellation_reason', 'cancelled_by_type', 'cancelled_by_id'])))
        ->toBe(['cancellation_reason' => CancellationReason::Customer, 'cancelled_by_type' => null, 'cancelled_by_id' => null]);

    // Paid: only the store can cancel it now.
    guestOrderRequest('POST', "{$paid->public_id}/cancel", $paidToken)->assertConflict()->assertJsonPath('code', 'order_already_paid');
    expect($this->store->run(static fn (): string => Order::query()->whereKey($paid->id)->sole()->status->value))->toBe('open');
});

it('gives a guest their order\'s invoice and credit notes with its token', function (): void {
    [$order, $invoice, $creditNote] = invoicedOrder();
    [$other, , $otherCreditNote] = invoicedOrder();
    $token = guestOrderToken($order);
    $otherToken = guestOrderToken($other);

    expect(guestOrderRequest('GET', "{$order->public_id}/invoice", $token)->assertOk()->json('data'))->toMatchArray(['id' => $invoice->public_id, 'number' => 'INV-2026-000001'])
        ->and(guestOrderRequest('GET', "{$order->public_id}/credit-notes/{$creditNote->public_id}", $token)->assertOk()->json('data.number'))->toBe('CN-2026-000001')
        ->and(guestOrderRequest('GET', "{$order->public_id}/invoice/pdf", $token)->assertOk()->headers->get('Content-Disposition'))->toContain('INV-2026-000001.pdf')
        ->and(guestOrderRequest('GET', "{$order->public_id}/credit-notes/{$creditNote->public_id}/pdf", $token)->assertOk()->headers->get('Content-Disposition'))->toContain('CN-2026-000001.pdf');

    foreach (["{$order->public_id}/invoice", "{$order->public_id}/invoice/pdf", "{$order->public_id}/credit-notes/{$creditNote->public_id}", "{$order->public_id}/credit-notes/{$creditNote->public_id}/pdf"] as $path) {
        guestOrderRequest('GET', $path, null)->assertNotFound();
        guestOrderRequest('GET', $path, $otherToken)->assertNotFound();
        guestOrderRequest('GET', $path.'?'.http_build_query(['token' => $token]), null)->assertNotFound();
    }

    // Another order's credit note through this order: not found.
    guestOrderRequest('GET', "{$order->public_id}/credit-notes/{$otherCreditNote->public_id}", $token)->assertNotFound();
    guestOrderRequest('GET', "{$order->public_id}/credit-notes/{$otherCreditNote->public_id}/pdf", $token)->assertNotFound();
});

it('lets a guest pay again with their token, their Idempotency-Keys their own', function (): void {
    useFakeGateway();
    // Live, as the orders are.
    connectFakeGateway('live');
    offerManualMethod(PaymentMethod::BankTransfer);
    [$order, $other] = $this->store->run(static fn (): array => [unpaidOrder(paymentDueAt: CarbonImmutable::now()->addDays(3)), unpaidOrder(paymentDueAt: CarbonImmutable::now()->addDays(3))]);
    $token = guestOrderToken($order);
    $otherToken = guestOrderToken($other);
    $payAgain = static fn (Order $for, ?string $with, array $data, ?string $key = 'guest-pay-0001') => guestOrderRequest('POST', "{$for->public_id}/payments", $with, $data, $key === null ? [] : ['Idempotency-Key' => $key]);

    $payAgain($order, $token, ['payment_method' => 'bank_transfer'], key: null)->assertBadRequest()->assertJsonPath('code', 'idempotency_key_missing');
    // Nobody known: no order token, no cart.
    $payAgain($order, null, ['payment_method' => 'bank_transfer'])->assertUnauthorized()->assertJsonPath('code', 'idempotency_requester_unknown');
    $payAgain($order, $otherToken, ['payment_method' => 'bank_transfer'], key: 'guest-pay-other')->assertNotFound();
    $payAgain($order, $token, ['payment_method' => 'gateway:'.FakePaymentGateway::KEY])->assertUnprocessable()->assertJsonPath('code', 'return_url_required');

    $paid = $payAgain($order, $token, ['payment_method' => 'gateway:'.FakePaymentGateway::KEY, 'return_url' => 'https://shop.example.com/thanks'], key: 'guest-pay-0002')->assertCreated();
    expect($paid->json('data.method'))->toBe('gateway')
        ->and($paid->json('data.redirect_url'))->toBeString()
        ->and(app(FakeGatewayScript::class)->startedInTransactionLevels)->toBe([0]);

    // A retry replays the same payment; the same key from another guest is theirs alone.
    $replayed = $payAgain($order, $token, ['payment_method' => 'gateway:'.FakePaymentGateway::KEY, 'return_url' => 'https://shop.example.com/thanks'], key: 'guest-pay-0002')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($replayed->json('data.reference'))->toBe($paid->json('data.reference'));
    $theirs = $payAgain($other, $otherToken, ['payment_method' => 'bank_transfer'], key: 'guest-pay-0002')->assertCreated();
    expect($theirs->headers->has('Idempotent-Replayed'))->toBeFalse()
        ->and($theirs->json('data.method'))->toBe('bank_transfer')
        ->and($this->store->run(static fn (): array => Payment::query()->orderBy('id')->pluck('order_id')->all()))->toBe([$order->id, $other->id]);

    // Keys are kept under the token's hash: the token itself is a secret.
    $scopes = $this->store->run(static fn (): string => IdempotencyKey::query()->pluck('scope')->implode(' '));
    expect($scopes)->toContain('order:')->not->toContain($token)->not->toContain($otherToken);
});

it('limits a guest\'s order requests per token, under a ceiling per IP address', function (): void {
    config(['api.rate_limits.guest_order' => 2, 'api.rate_limits.guest_order_ip_ceiling' => 3]);
    [$order, $other] = $this->store->run(static fn (): array => [unpaidOrder(), unpaidOrder()]);
    $token = guestOrderToken($order);
    $otherToken = guestOrderToken($other);
    $from = static fn (Order $for, string $with, string $ip = '203.0.113.20') => guestOrderRequest('GET', $for->public_id, $with, ip: $ip);

    $from($order, $token)->assertOk();
    $from($order, $token)->assertOk();
    $from($order, $token)->assertTooManyRequests();
    // Another order from the same address, until the address's ceiling.
    $from($other, $otherToken)->assertOk();
    $from($other, $otherToken)->assertTooManyRequests();
    // Elsewhere, the first token's own limit still counts.
    $from($order, $token, '198.51.100.7')->assertTooManyRequests();
});
