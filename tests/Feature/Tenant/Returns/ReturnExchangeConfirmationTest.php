<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Money\Money;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Actions\ChangeReturnSettings;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnExchange;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: previewing and confirming a return's exchange, with the owner's
 * decisions. When the store's fault is confirmed, a like-for-like
 * replacement (the same product at the same price today, or at what the
 * buyer paid when the returned variant has no price today) costs nothing
 * beyond what the returned units did, and its delivery is free. Otherwise
 * today's price with today's automatic promotions, and the delivery is
 * charged only when the return settings say so. Confirming places the
 * replacement as previewed and the return waits for it to be paid.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $this->order = returnableOrder([[10000, 1]], $this->customer, paidByHand: true);
    $this->returned = $this->store->run(fn (): ProductVariant => ProductVariant::query()->with('product')->findOrFail($this->order->lines->sole()->product_variant_id));
    $this->method = replacementShippingMethod(1500);
});

afterEach(function (): void {
    deleteAllStores();
});

it('prices a replacement at today\'s price when the store wasn\'t at fault, charging delivery only when the settings say so', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)), ReturnReason::DoesNotFit);

    exchangePreviewAs($return, $this->owner, $this->method)->assertOk()
        ->assertJsonPath('data.stores_fault', false)
        ->assertJsonPath('data.lines.0.free_like_for_like', false)
        ->assertJsonPath('data.shipping.charged', false)
        ->assertJsonPath('data.total.amount', 12000)
        ->assertJsonPath('data.credit.amount', 10000)
        ->assertJsonPath('data.amount_due.amount', 2000)
        ->assertJsonPath('data.difference.amount', 0)
        ->assertJsonPath('data.issues', []);

    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(chargeShippingOnExchanges: true));

    exchangePreviewAs($return, $this->owner, $this->method)->assertOk()
        ->assertJsonPath('data.shipping.charged', true)
        ->assertJsonPath('data.total.amount', 13500)
        ->assertJsonPath('data.amount_due.amount', 3500);
});

it('sends a like-for-like replacement free when the store\'s fault is confirmed, whatever today\'s price', function (int $todaysPrice, array $settled): void {
    $sibling = $this->store->run(fn (): ProductVariant => siblingVariant($this->returned, 10000));
    $this->store->run(fn () => $this->returned->forceFill(['price' => Money::ofMinor($todaysPrice, 'NGN')])->save());
    $this->store->run(static fn () => $sibling->forceFill(['price' => Money::ofMinor($todaysPrice, 'NGN')])->save());
    $this->store->run(static fn () => app(ChangeReturnSettings::class)->handle(chargeShippingOnExchanges: true));
    $return = inspectedExchange($this, $sibling, ReturnReason::Faulty);

    $preview = exchangePreviewAs($return, $this->owner, $this->method)->assertOk()
        ->assertJsonPath('data.stores_fault', true)
        ->assertJsonPath('data.lines.0.free_like_for_like', true)
        ->assertJsonPath('data.shipping.charged', false);

    expect([$preview->json('data.total.amount'), $preview->json('data.credit.amount'), $preview->json('data.amount_due.amount'), $preview->json('data.difference.amount'), $preview->json('data.kept.amount')])->toBe($settled);
})->with([
    'dearer today' => [12000, [10000, 10000, 0, 0, 0]],
    'cheaper today' => [8000, [8000, 8000, 0, 0, 2000]],
]);

it('compares with what the buyer paid when the returned variant has no price today', function (): void {
    $sibling = $this->store->run(fn (): ProductVariant => siblingVariant($this->returned, 10000));
    $this->store->run(fn () => $this->returned->delete());
    $return = inspectedExchange($this, $sibling, ReturnReason::Damaged);

    exchangePreviewAs($return, $this->owner, $this->method)->assertOk()->assertJsonPath('data.lines.0.free_like_for_like', true);

    // At another price than what was paid, it isn't like-for-like.
    $this->store->run(static fn () => $sibling->forceFill(['price' => Money::ofMinor(11000, 'NGN')])->save());

    exchangePreviewAs($return, $this->owner, $this->method)->assertOk()
        ->assertJsonPath('data.lines.0.free_like_for_like', false)
        ->assertJsonPath('data.total.amount', 11000);
});

it('is free like-for-like only with a confirmed fault and the same product', function (Closure $replacement, ReturnReason $reason, ?string $overruled): void {
    $variant = $this->store->run(fn (): ProductVariant => $replacement($this));
    $return = inspectedExchange($this, $variant, $reason, $overruled);

    exchangePreviewAs($return, $this->owner, $this->method)->assertOk()->assertJsonPath('data.lines.0.free_like_for_like', false);
})->with([
    'another product at the same price' => [fn ($test) => cartVariant(10000, stock: 5), ReturnReason::Damaged, null],
    'the same product, the fault overruled' => [fn ($test) => siblingVariant($test->returned, 10000), ReturnReason::Damaged, 'Damaged by the buyer'],
    'the same product, no fault claimed' => [fn ($test) => siblingVariant($test->returned, 10000), ReturnReason::ChangedMind, null],
]);

it('confirms the exchange as previewed: the replacement is placed and the return waits for it to be paid', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5));
    $return = inspectedExchange($this, $variant, ReturnReason::DoesNotFit);

    $response = confirmExchangeAs($return, $this->owner, $this->method, idempotencyKey: 'exchange-0001')->assertOk()
        ->assertJsonPath('data.status', 'awaiting_exchange')
        ->assertJsonPath('data.exchange.status', 'pending')
        ->assertJsonPath('data.exchange.credit.amount', 10000);

    [$exchange, $order] = $this->store->run(static fn (): array => [ReturnExchange::query()->sole(), Order::query()->where('replaces_return_id', $return->id)->sole()]);

    expect($response->json('data.exchange.order.id'))->toBe($order->public_id)
        ->and($order->customer_id)->toBe($this->customer->id)
        ->and($order->total->minorAmount())->toBe(12000)
        ->and($order->payment_status->value)->not->toBe('paid')
        ->and([$exchange->returned_value->minorAmount(), $exchange->credit->minorAmount(), $exchange->difference->minorAmount(), $exchange->kept->minorAmount()])->toBe([10000, 10000, 0, 0]);

    // Confirming again returns the same exchange.
    confirmExchangeAs($return, $this->owner, $this->method, hash: str_repeat('0', 64))->assertOk();

    expect($this->store->run(static fn (): array => [ReturnExchange::query()->count(), Order::query()->whereNotNull('replaces_return_id')->count()]))->toBe([1, 1]);
});

it('places nothing when the preview no longer stands, or a replacement can\'t be sent, and shows the new preview', function (): void {
    $variant = $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 1));
    $return = inspectedExchange($this, $variant, ReturnReason::DoesNotFit);
    $shown = (string) exchangePreviewAs($return, $this->owner, $this->method)->json('data.quote_hash');
    $this->store->run(static fn () => $variant->forceFill(['price' => Money::ofMinor(13000, 'NGN')])->save());

    confirmExchangeAs($return, $this->owner, $this->method, hash: $shown)->assertConflict()
        ->assertJsonPath('code', 'replacement_quote_changed')
        ->assertJsonPath('data.total.amount', 13000);

    $this->store->run(static fn () => app(App\Tenant\Inventory\Actions\ReserveStock::class)->handle(new App\Tenant\Inventory\Data\StockHolder('checkout', 'someone-else'), [new App\Tenant\Inventory\Data\StockLine($variant, 1)], now()->toImmutable()->addMinutes(15)));

    confirmExchangeAs($return, $this->owner, $this->method)->assertConflict()
        ->assertJsonPath('code', 'replacement_out_of_stock')
        ->assertJsonPath('data.issues', ['replacement_out_of_stock']);

    expect($this->store->run(static fn (): array => [ReturnExchange::query()->count(), Order::query()->whereNotNull('replaces_return_id')->count(), OrderReturn::query()->findOrFail($return->id)->status->value]))
        ->toBe([0, 0, 'inspected']);
});

it('refuses to preview or confirm an exchange that isn\'t ready', function (): void {
    $return = requestedExchange($this->order, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)));

    exchangePreviewAs($return, $this->owner, $this->method)->assertConflict()->assertJsonPath('code', 'exchange_not_ready');
    returnsRequest('POST', "{$return->public_id}/exchange", ['quote_hash' => str_repeat('0', 64)], as: $this->owner, headers: ['Idempotency-Key' => 'not-ready-0001'])
        ->assertConflict()->assertJsonPath('code', 'exchange_not_ready');
});

it('needs the sensitive returns.exchange permission, and payments.refund, to confirm', function (): void {
    $return = inspectedExchange($this, $this->store->run(static fn (): ProductVariant => cartVariant(12000, stock: 5)), ReturnReason::DoesNotFit);
    $viewer = returnsStaffWith($this->store, ['returns.view', 'returns.manage', 'payments.refund']);
    $exchanger = returnsStaffWith($this->store, ['returns.view', 'returns.exchange']);

    exchangePreviewAs($return, $viewer, $this->method)->assertOk();
    confirmExchangeAs($return, $viewer, $this->method)->assertForbidden();
    confirmExchangeAs($return, $exchanger, $this->method)->assertForbidden();

    expect(App\Tenant\Returns\Enums\ReturnPermission::ReturnsExchange->isSensitive())->toBeTrue()
        ->and(App\Tenant\Returns\Enums\ReturnPermission::ReturnsManage->isSensitive())->toBeFalse();
});
