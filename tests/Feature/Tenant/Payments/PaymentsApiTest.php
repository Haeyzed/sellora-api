<?php

declare(strict_types=1);

use App\Shared\Money\Money;
use App\Shared\Privacy\PersonalDataErasure;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Services\OrderAnonymisation;
use App\Tenant\Payments\Actions\ChangeManualPaymentMethod;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\RefundOrder;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Data\GatewayRefundResult;
use App\Tenant\Payments\Data\RefundRequest;
use App\Tenant\Payments\Enums\GatewayMode;
use App\Tenant\Payments\Enums\GatewayPaymentState;
use App\Tenant\Payments\Enums\GatewayRefundState;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Enums\PaymentPermission;
use App\Tenant\Payments\Jobs\CheckPaymentWithGateway;
use App\Tenant\Payments\Jobs\CheckRefundWithGateway;
use App\Tenant\Payments\Models\Payment;
use App\Tenant\Payments\Models\Refund;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 15.6: the staff API for payments and refunds. Staff with
 * payments.view see payments (with what went back from them and their
 * history) and refunds; payments.confirm records bank transfers and cash
 * collected, each only for its own method, and asks a gateway again;
 * payments.refund refunds an order, once per Idempotency-Key. An anonymised
 * order's payments forget their card details. Nothing is found across stores.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('orders-store', ['country_code' => 'NG']);
    [$this->owner, $this->viewer] = $this->store->run(static fn (): array => orderStaff());
    $this->script = useFakeGateway();
    connectFakeGateway('live');
    Queue::fake([CheckPaymentWithGateway::class, CheckRefundWithGateway::class]);
});

afterEach(function (): void {
    deleteAllStores();
});

it('lists payments newest first, paged by cursor, narrowed by status, method or mode', function (): void {
    [, $paid] = gatewayPaidOrder();
    [, $transfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    $ids = static fn (string $query = ''): array => staffOrderRequest('GET', '/payments'.$query)->assertOk()->json('data.*.id');

    expect($ids())->toBe([$transfer->public_id, $paid->public_id])
        ->and($ids('?status=pending'))->toBe([$transfer->public_id])
        ->and($ids('?method=gateway'))->toBe([$paid->public_id])
        ->and($ids('?is_test=1'))->toBe([])
        ->and(staffOrderRequest('GET', '/payments?per_page=1')->assertOk()->json('meta.next_cursor'))->toBeString();
});

it('shows a payment with what arrived, what went back and its history, and lists an order\'s payments', function (): void {
    [$order, $payment] = gatewayPaidOrder();
    // Another order's payment never shows among this order's.
    gatewayPaidOrder();
    refundOrder($order, 1500);
    // Still on its way back, so not refunded yet.
    $this->script->refundAnswer = new GatewayRefundResult(GatewayRefundState::Pending);
    refundOrder($order, 2000);

    staffOrderRequest('GET', "/payments/{$payment->public_id}")->assertOk()
        ->assertJsonPath('data.order.id', $order->public_id)
        ->assertJsonPath('data.method', 'gateway')
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.received.amount', 11500)
        ->assertJsonPath('data.refunded.amount', 1500)
        ->assertJsonPath('data.events.*.type', ['started', 'succeeded', 'refund_started', 'refunded', 'refund_started']);

    expect(staffOrderRequest('GET', "/orders/{$order->public_id}/payments")->assertOk()->json('data.*.id'))->toBe([$payment->public_id]);
});

it('lets staff with payments.view look, payments.confirm record money and payments.refund give it back, and nobody else', function (): void {
    [$order, $payment] = gatewayPaidOrder();
    [, $transfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    $looker = paymentsStaff([PaymentPermission::PaymentsView]);
    $refund = static fn (StaffMember $as) => staffOrderRequest('POST', "/orders/{$order->public_id}/refunds", ['amount' => 100], $as, ['Idempotency-Key' => 'refund-key-'.$as->public_id]);

    foreach (['/payments', '/refunds', "/payments/{$payment->public_id}", "/orders/{$order->public_id}/payments"] as $path) {
        staffOrderRequest('GET', $path, as: $this->viewer)->assertForbidden();
        staffOrderRequest('GET', $path, as: $looker)->assertOk();
    }

    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => 100], $looker)->assertForbidden();
    staffOrderRequest('POST', "/payments/{$payment->public_id}/checks", as: $looker)->assertForbidden();
    $refund($looker)->assertForbidden();

    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => 100], paymentsStaff([PaymentPermission::PaymentsConfirm]))->assertOk();
    $refund(paymentsStaff([PaymentPermission::PaymentsRefund]))->assertCreated();
});

it('records a bank transfer received and cash collected, each only for its own method, once', function (): void {
    [, $transfer] = manuallyPaidOrder(PaymentMethod::BankTransfer);
    [, $cash] = manuallyPaidOrder(PaymentMethod::CashOnDelivery);

    staffOrderRequest('POST', "/payments/{$transfer->public_id}/collections", ['amount' => 4000])->assertConflict()->assertJsonPath('code', 'payment_not_awaiting_receipt');
    staffOrderRequest('POST', "/payments/{$cash->public_id}/receipts", ['amount' => 4000])->assertConflict()->assertJsonPath('code', 'payment_not_awaiting_receipt');
    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => '4000'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => 0])->assertUnprocessable()->assertJsonValidationErrors('amount');

    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => 4000, 'reference' => 'TRF-1'])->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.received.amount', 4000)
        ->assertJsonPath('data.confirmed_by', ['type' => 'staff_member', 'id' => $this->owner->public_id]);
    staffOrderRequest('POST', "/payments/{$cash->public_id}/collections", ['amount' => 11500])->assertOk()->assertJsonPath('data.status', 'succeeded');

    staffOrderRequest('POST', "/payments/{$transfer->public_id}/receipts", ['amount' => 4000])->assertConflict()->assertJsonPath('code', 'payment_not_awaiting_receipt');
});

it('asks the gateway again about a payment when staff ask', function (): void {
    $payment = $this->store->run(static fn (): Payment => startFakePayment(unpaidOrder()));

    staffOrderRequest('POST', "/payments/{$payment->public_id}/checks")->assertOk()->assertJsonPath('data.status', 'pending');

    gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live);
    staffOrderRequest('POST', "/payments/{$payment->public_id}/checks")->assertOk()->assertJsonPath('data.status', 'succeeded');

    expect($this->script->timesCalled('verifyPayment', $payment->reference))->toBe(2);
});

it('refunds an order by amount or by lines, once per Idempotency-Key', function (): void {
    [$order, $payment] = gatewayPaidOrder();
    $line = $this->store->run(static fn (): string => $order->lines()->sole()->public_id);
    $refund = static fn (array $data, ?string $key) => staffOrderRequest('POST', "/orders/{$order->public_id}/refunds", $data, headers: $key === null ? [] : ['Idempotency-Key' => $key]);

    $refund(['amount' => 1500], null)->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_missing');

    $first = $refund(['amount' => 1500, 'reason' => 'Late delivery'], 'refund-key-1')->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.amount.amount', 1500)
        ->assertJsonPath('data.reason', 'Late delivery')
        ->assertJsonPath('data.parts.0.payment', $payment->public_id)
        ->json('data.id');
    expect($refund(['amount' => 1500, 'reason' => 'Late delivery'], 'refund-key-1')->assertCreated()->json('data.id'))->toBe($first);

    $refund(['amount' => 100, 'lines' => [['line' => $line, 'quantity' => 1]]], 'refund-key-2')->assertUnprocessable()->assertJsonValidationErrors('amount');
    $refund(['lines' => [['line' => $line, 'quantity' => 1]], 'shipping' => true], 'refund-key-3')->assertUnprocessable()->assertJsonPath('code', 'refund_exceeds_refundable');
    $refund(['lines' => [['line' => $line, 'quantity' => 1]]], 'refund-key-4')->assertCreated()
        ->assertJsonPath('data.amount.amount', 10000)
        ->assertJsonPath('data.lines.0.line', $line)
        ->assertJsonPath('data.lines.0.quantity', 1)
        ->assertJsonPath('data.lines.0.amount.amount', 10000)
        ->assertJsonPath('data.lines.0.tax.amount', 0);

    expect($this->store->run(static fn (): array => [Refund::query()->count(), $order->refresh()->payment_status->value]))->toBe([2, 'refunded']);
});

it('lists refunds newest first, and shows one with each part', function (): void {
    [$order] = gatewayPaidOrder();
    $older = refundOrder($order, 1000);
    $newer = refundOrder($order, 2000);

    expect(staffOrderRequest('GET', '/refunds')->assertOk()->json('data.*.id'))->toBe([$newer->public_id, $older->public_id]);

    staffOrderRequest('GET', "/refunds/{$older->public_id}")->assertOk()
        ->assertJsonPath('data.order.id', $order->public_id)
        ->assertJsonPath('data.requested_by', ['type' => 'staff_member', 'id' => $this->owner->public_id])
        ->assertJsonPath('data.parts.0.status', 'succeeded')
        ->assertJsonPath('data.parts.0.amount.amount', 1000);
});

it('forgets a payment\'s card details when its order is anonymised, keeping the payment', function (): void {
    $payment = $this->store->run(static function (): Payment {
        $payment = startFakePayment(unpaidOrder());
        gatewayAnswers($payment, GatewayPaymentState::Succeeded, $payment->amount, GatewayMode::Live, '4242');

        return verifyFakePayment($payment);
    });

    expect([$payment->card_brand, $payment->card_last4])->toBe(['visa', '4242']);

    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn () => app(OrderAnonymisation::class)->anonymise($payment->order)));

    expect($this->store->run(static fn (): array => [$payment->refresh()->card_brand, $payment->card_last4, $payment->status->value, $payment->received?->minorAmount()]))->toBe([null, null, 'succeeded', 11500]);
});

it('finds another store\'s payments and refunds nowhere', function (): void {
    $other = createStore('other-store', ['country_code' => 'NG']);
    [$paymentId, $refundId] = $other->run(static function (): array {
        $owner = StaffMember::factory()->create();
        app(ChangeManualPaymentMethod::class)->handle($owner, PaymentMethod::BankTransfer, ['enabled' => true]);
        $order = unpaidOrder(paymentDueAt: now()->toImmutable()->addDay());
        $payment = app(StartManualPayment::class)->handle($order, PaymentMethod::BankTransfer);
        app(RecordManualPayment::class)->handle($owner, $payment, Money::ofMinor(11500, 'NGN'));
        $refund = app(RefundOrder::class)->handle($owner, $order, new RefundRequest(Money::ofMinor(1000, 'NGN')));

        return [$payment->public_id, $refund->public_id];
    });

    staffOrderRequest('GET', "/payments/{$paymentId}")->assertNotFound();
    staffOrderRequest('POST', "/payments/{$paymentId}/receipts", ['amount' => 100])->assertNotFound();
    staffOrderRequest('GET', "/refunds/{$refundId}")->assertNotFound();
    expect(staffOrderRequest('GET', '/payments')->assertOk()->json('data'))->toBe([])
        ->and(staffOrderRequest('GET', '/refunds')->assertOk()->json('data'))->toBe([]);
});
