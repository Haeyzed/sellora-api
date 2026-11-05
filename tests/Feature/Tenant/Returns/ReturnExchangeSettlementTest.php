<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Checkout\Events\ExchangeCreditCommitted;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Models\CreditNote;
use App\Tenant\Orders\Models\Invoice;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Payments\Actions\RecordManualPayment;
use App\Tenant\Payments\Actions\StartManualPayment;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Exceptions\RefundDestinationRefusedException;
use App\Tenant\Payments\Models\Refund;
use App\Tenant\Returns\Actions\SettleExchange;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Events\ReturnExchangeReleased;
use App\Tenant\Returns\Events\ReturnInspected;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnExchange;
use App\Tenant\Returns\Models\ReturnRefund;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeStoreCreditDestination;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: settling an exchange. Once its replacement is paid, the original
 * order gets a refund of the returned lines to core's exchange destination
 * for what they credited (the rest kept back from it, so its credit note
 * credits exactly that), once; a cheaper replacement's difference goes back
 * to the buyer, to the destination they chose, through the return's refund
 * attempts; and the return completes. A replacement that expires unpaid
 * sends the return back to where it was, free for another exchange.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    useStoreCreditDestination();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $this->order = returnableOrder([[10000, 1]], $this->customer, paidByHand: true);
    $this->returned = $this->store->run(fn (): ProductVariant => ProductVariant::query()->with('product')->findOrFail($this->order->lines->sole()->product_variant_id));
    $this->method = replacementShippingMethod(1500);
});

afterEach(function (): void {
    deleteAllStores();
    FakeStoreCreditDestination::reset();
});

it('settles a cheaper replacement at once: the credit to the exchange, the difference to where the customer chose', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(6000, stock: 5)), ReturnReason::DoesNotFit);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();

    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    [$status, $exchange, $toExchange, $difference, $credited] = settledExchange($return);

    expect($status)->toBe('completed')
        ->and([$exchange->status->value, $exchange->credit->minorAmount(), $exchange->difference->minorAmount(), $exchange->kept->minorAmount()])->toBe(['committed', 6000, 4000, 0])
        ->and([$toExchange->destination, $toExchange->amount->minorAmount(), $toExchange->withheld->minorAmount()])->toBe(['exchange', 6000, 4000])
        ->and([$difference?->destination, $difference?->refund_amount, $difference?->status->value])->toBe(['store_credit', 4000, 'succeeded'])
        ->and(array_column(FakeStoreCreditDestination::$asked, 'amount'))->toBe([4000])
        // Credit notes for all the returned items were worth: 6000 to the replacement, 4000 back to the buyer.
        ->and($credited)->toBe(10000);
});

it('keeps what a free like-for-like replacement didn\'t use, and gives nothing back', function (): void {
    $sibling = $this->store->run(fn (): ProductVariant => siblingVariant($this->returned, 10000));
    $this->store->run(fn () => [$this->returned->forceFill(['price' => Money::ofMinor(8000, 'NGN')])->save(), $sibling->forceFill(['price' => Money::ofMinor(8000, 'NGN')])->save()]);
    $return = inspectedExchange($this, $sibling, ReturnReason::Damaged);

    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    [$status, $exchange, $toExchange, $difference, $credited] = settledExchange($return);

    expect($status)->toBe('completed')
        ->and([$exchange->credit->minorAmount(), $exchange->difference->minorAmount(), $exchange->kept->minorAmount()])->toBe([8000, 0, 2000])
        ->and([$toExchange->amount->minorAmount(), $toExchange->withheld->minorAmount()])->toBe([8000, 2000])
        ->and($difference)->toBeNull()
        ->and($credited)->toBe(8000);
});

it('waits for a dearer replacement to be paid, then settles it with the replacement\'s invoice', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)), ReturnReason::DoesNotFit);
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk()->assertJsonPath('data.status', 'awaiting_exchange');

    expect($this->store->run(static fn (): int => Refund::query()->count()))->toBe(0);

    $this->store->run(function (): void {
        $replacement = Order::query()->whereNotNull('replaces_return_id')->sole();
        app(App\Tenant\Payments\Actions\ChangeManualPaymentMethod::class)->handle($this->owner, PaymentMethod::BankTransfer, ['enabled' => true]);
        app(RecordManualPayment::class)->handle($this->owner, app(StartManualPayment::class)->handle($replacement, PaymentMethod::BankTransfer), Money::ofMinor(2000, 'NGN'));
    });

    [$status, $exchange, $toExchange] = settledExchange($return);
    $invoiced = $this->store->run(static fn (): bool => Invoice::query()->where('order_id', $exchange->order_id)->exists());

    expect($status)->toBe('completed')
        ->and([$toExchange->amount->minorAmount(), $toExchange->withheld->minorAmount()])->toBe([10000, 0])
        ->and($invoiced)->toBeTrue();
});

it('sends the return back where it was when its replacement expires unpaid, and lets staff confirm again', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5));
    $return = inspectedExchange($this, $variant, ReturnReason::DoesNotFit);
    $inspectedAt = $this->store->run(static fn () => OrderReturn::query()->findOrFail($return->id)->inspected_at);
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();
    Event::fake([ReturnExchangeReleased::class, ReturnInspected::class]);

    $this->store->run(static fn () => app(CancelOrder::class)->handle(Order::query()->whereNotNull('replaces_return_id')->sole(), CancellationReason::PaymentExpired, null));

    $after = $this->store->run(static fn (): array => [OrderReturn::query()->findOrFail($return->id), ReturnExchange::query()->sole()]);

    expect($after[0]->status->value)->toBe('inspected')
        ->and($after[0]->inspected_at->equalTo($inspectedAt))->toBeTrue()
        ->and($after[1]->status->value)->toBe('released');

    Event::assertDispatched(ReturnExchangeReleased::class);
    Event::assertNotDispatched(ReturnInspected::class);

    confirmExchangeAs($return, $this->owner, $this->method)->assertOk()->assertJsonPath('data.status', 'awaiting_exchange');

    expect($this->store->run(static fn (): array => ReturnExchange::query()->orderBy('id')->pluck('status')->map->value->all()))->toBe(['released', 'pending']);
});

it('sends a kept return back to approved when its replacement is cancelled unpaid', function (): void {
    $return = requestedExchange($this->order, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)));
    returnsRequest('POST', "{$return->public_id}/approve", ['keep_items' => true], as: $this->owner)->assertOk();
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    $this->store->run(static fn () => app(CancelOrder::class)->handle(Order::query()->whereNotNull('replaces_return_id')->sole(), CancellationReason::PaymentExpired, null));

    expect($this->store->run(static fn (): string => OrderReturn::query()->findOrFail($return->id)->status->value))->toBe('approved');
});

it('reopens the choice after the difference definitely failed, and staff settle it again from the return', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(6000, stock: 5)), ReturnReason::DoesNotFit);
    chooseRefundDestinationAsCustomer($return, $this->customer, FakeStoreCreditDestination::KEY)->assertOk();
    FakeStoreCreditDestination::$failure = new RefundDestinationRefusedException;

    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    expect($this->store->run(static fn (): array => [OrderReturn::query()->findOrFail($return->id)->status->value, ReturnRefund::query()->sole()->status->value]))->toBe(['awaiting_exchange', 'failed']);

    FakeStoreCreditDestination::$failure = null;
    chooseRefundDestinationAsCustomer($return, $this->customer, 'original_payment')->assertOk();
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');

    expect($this->store->run(static fn (): array => ReturnRefund::query()->orderBy('attempt')->get()->map(static fn (ReturnRefund $attempt): array => [$attempt->destination, $attempt->status->value])->all()))
        ->toBe([['store_credit', 'failed'], ['original_payment', 'succeeded']]);
});

it('never refunds twice, however often the exchange is settled', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(6000, stock: 5)), ReturnReason::DoesNotFit);
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    $this->store->run(static function (): void {
        $exchange = ReturnExchange::query()->sole();
        app(SettleExchange::class)->committed($exchange);
        app(SettleExchange::class)->committed($exchange->refresh());
    });
    refundReturnAs($return, $this->owner)->assertOk();

    expect($this->store->run(static fn (): array => [Refund::query()->count(), ReturnRefund::query()->count(), CreditNote::query()->count()]))->toBe([2, 1, 2]);
});

it('makes the refund to the exchange once, even when settling stopped right after making it', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(10000, stock: 5)), ReturnReason::DoesNotFit);
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    $refunds = $this->store->run(static function (): int {
        // As if settling stopped after Payments made the refund, before the exchange recorded it.
        $exchange = ReturnExchange::query()->sole();
        $exchange->forceFill(['status' => 'pending', 'committed_at' => null, 'refund_id' => null])->save();
        app(SettleExchange::class)->committed($exchange->refresh());

        return Refund::query()->count();
    });

    expect($refunds)->toBe(1)
        ->and($this->store->run(static fn (): string => ReturnExchange::query()->sole()->status->value))->toBe('committed');
});

it('lets staff settle a paid exchange from the return when settling didn\'t run', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(10000, stock: 5)), ReturnReason::DoesNotFit);
    Event::fake([ExchangeCreditCommitted::class]);

    confirmExchangeAs($return, $this->owner, $this->method)->assertOk()->assertJsonPath('data.status', 'awaiting_exchange');
    refundReturnAs($return, $this->owner)->assertOk()->assertJsonPath('data.status', 'completed');

    expect($this->store->run(static fn (): array => [Refund::query()->sole()->destination, ReturnExchange::query()->sole()->status->value]))->toBe(['exchange', 'committed']);
});

it('settles nothing while the replacement isn\'t paid', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)), ReturnReason::DoesNotFit);
    confirmExchangeAs($return, $this->owner, $this->method)->assertOk();

    refundReturnAs($return, $this->owner)->assertConflict();

    expect($this->store->run(static fn (): array => [Refund::query()->count(), ReturnExchange::query()->sole()->status->value]))->toBe([0, 'pending']);
});
