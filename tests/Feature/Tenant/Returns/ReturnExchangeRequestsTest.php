<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Returns\Models\OrderReturn;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: asking for an exchange. A buyer or staff ask for a return with
 * resolution exchange, naming a variant to send in place of every returned
 * line; each must be for sale and in stock (or take backorders), and none is
 * held until the exchange is confirmed. Approval checks them again. Staff
 * can choose other replacements, or change how the return is settled, only
 * before the exchange is confirmed. An exchange is never refunded as one.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    $this->order = returnableOrder([[10000, 1]], $this->customer, paidByHand: true);
    $this->line = $this->order->lines->sole()->public_id;
    $this->replacement = $this->store->run(static fn (): ProductVariant => cartVariant(10000, stock: 3));
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets a customer ask for an exchange, naming what to send, and holds no stock for it', function (): void {
    $response = customerReturnRequest('POST', "orders/{$this->order->public_id}/returns", $this->customer, [
        'lines' => exchangeLinesPayload([$this->line => 1], [$this->line => $this->replacement->public_id]),
        'reason' => 'does_not_fit',
        'resolution' => 'exchange',
    ])->assertCreated()
        ->assertJsonPath('data.resolution', 'exchange')
        ->assertJsonPath('data.lines.0.replacement.id', $this->replacement->public_id);

    $variantId = $this->replacement->id;

    expect($this->store->run(static fn (): int => StockReservation::query()->where('product_variant_id', $variantId)->count()))->toBe(0)
        ->and($response->json('data.lines.0.replacement.sku'))->toBe($this->replacement->sku);
});

it('lets a guest ask for an exchange with their order\'s token', function (): void {
    $guestOrder = returnableOrder([[10000, 1]], paidByHand: true);
    $line = $guestOrder->lines->sole()->public_id;

    guestReturnRequest('POST', "{$guestOrder->public_id}/returns", guestOrderToken($guestOrder), [
        'lines' => exchangeLinesPayload([$line => 1], [$line => $this->replacement->public_id]),
        'reason' => 'does_not_fit',
        'resolution' => 'exchange',
    ])->assertCreated()->assertJsonPath('data.resolution', 'exchange');
});

it('refuses replacements that don\'t fit the resolution', function (string $resolution, bool $withReplacement): void {
    customerReturnRequest('POST', "orders/{$this->order->public_id}/returns", $this->customer, [
        'lines' => exchangeLinesPayload([$this->line => 1], $withReplacement ? [$this->line => $this->replacement->public_id] : []),
        'reason' => 'does_not_fit',
        'resolution' => $resolution,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'return_replacement_invalid');
})->with([
    'an exchange naming nothing' => ['exchange', false],
    'a refund naming a replacement' => ['refund', true],
]);

it('refuses a replacement that can\'t be sent, without saying how many are left', function (Closure $spoil): void {
    $variant = $this->replacement;
    $this->store->run(static fn () => $spoil($variant));

    $response = customerReturnRequest('POST', "orders/{$this->order->public_id}/returns", $this->customer, [
        'lines' => exchangeLinesPayload([$this->line => 1], [$this->line => $variant->public_id]),
        'reason' => 'does_not_fit',
        'resolution' => 'exchange',
    ])->assertConflict()->assertJsonPath('code', 'return_replacement_not_available');

    expect($response->getContent())->not->toContain('"available"')->not->toContain('on_hand');
})->with([
    'no longer for sale' => [fn (ProductVariant $variant) => $variant->product->forceFill(['status' => ProductStatus::Archived])->save()],
    'out of stock' => [fn (ProductVariant $variant) => app(App\Tenant\Inventory\Actions\ReserveStock::class)->handle(new App\Tenant\Inventory\Data\StockHolder('checkout', 'someone-else'), [new App\Tenant\Inventory\Data\StockLine($variant, 3)], now()->toImmutable()->addMinutes(15))],
]);

it('checks the replacements again on approval, and lets staff choose others', function (): void {
    $return = requestedExchange($this->order, $this->replacement);
    $variant = $this->replacement;
    $other = $this->store->run(static fn (): ProductVariant => cartVariant(10000, stock: 2));
    $this->store->run(static fn () => $variant->product->forceFill(['status' => ProductStatus::Archived])->save());

    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)
        ->assertConflict()
        ->assertJsonPath('code', 'return_replacement_not_available');

    returnsRequest('PUT', "{$return->public_id}/replacements", ['lines' => [['line' => $this->line, 'replacement' => $other->public_id]]], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.lines.0.replacement.id', $other->public_id);
    returnsRequest('POST', "{$return->public_id}/approve", [], as: $this->owner)->assertOk();
});

it('changes how a return is settled only before its exchange is confirmed, recording who did', function (): void {
    $return = requestedExchange($this->order, $this->replacement);

    returnsRequest('PUT', "{$return->public_id}/resolution", ['resolution' => 'refund'], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.resolution', 'refund')
        ->assertJsonPath('data.lines.0.replacement', null);
    returnsRequest('PUT', "{$return->public_id}/resolution", ['resolution' => 'exchange'], as: $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'return_replacement_invalid');
    returnsRequest('PUT', "{$return->public_id}/resolution", ['resolution' => 'exchange', 'lines' => [['line' => $this->line, 'replacement' => $this->replacement->public_id]]], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.resolution', 'exchange');

    $owner = $this->owner;
    $logged = $this->store->run(static fn (): array => Activity::query()->where('event', 'return_resolution_changed')->orderBy('id')->get()->map(static fn (Activity $activity): array => [$activity->causer_id === $owner->id, $activity->properties->all()])->all());

    expect($logged)->toBe([[true, ['from' => 'exchange', 'to' => 'refund']], [true, ['from' => 'refund', 'to' => 'exchange']]]);

    // Once refunded, it can no longer become an exchange.
    $refunded = inspectedReturn(returnableOrder([[10000, 1]], paidByHand: true));
    refundReturnAs($refunded, $this->owner)->assertOk();
    $refundedLine = $this->store->run(static fn (): string => OrderReturn::query()->findOrFail($refunded->id)->lines()->with('orderLine')->sole()->orderLine->public_id);

    returnsRequest('PUT', "{$refunded->public_id}/resolution", ['resolution' => 'exchange', 'lines' => [['line' => $refundedLine, 'replacement' => $this->replacement->public_id]]], as: $this->owner)
        ->assertConflict();

    // Nor while a refund of it may be on its way, before the return is finished.
    $pending = inspectedReturn(returnableOrder([[10000, 1]], paidByHand: true));
    $pendingLine = $this->store->run(static function () use ($pending): string {
        $locked = OrderReturn::query()->findOrFail($pending->id);
        (new App\Tenant\Returns\Models\ReturnRefund)->forceFill([
            'return_id' => $locked->id, 'attempt' => 1, 'idempotency_key' => App\Tenant\Returns\Models\ReturnRefund::keyFor($locked, 1), 'status' => 'started',
            'shipping' => false, 'destination' => 'original_payment', 'requested_by_type' => 'staff_member', 'requested_by_id' => 'someone',
        ])->save();

        return $locked->lines()->with('orderLine')->sole()->orderLine->public_id;
    });

    returnsRequest('PUT', "{$pending->public_id}/resolution", ['resolution' => 'exchange', 'lines' => [['line' => $pendingLine, 'replacement' => $this->replacement->public_id]]], as: $this->owner)
        ->assertConflict();
});

it('never refunds an exchange as one: its resolution must change first', function (): void {
    $return = requestedExchange($this->order, $this->replacement);
    $this->store->run(static function () use ($return): void {
        $staff = App\Shared\Auth\AccountReference::to(App\Tenant\Identity\Models\StaffMember::query()->firstOrFail());
        app(App\Tenant\Returns\Actions\ApproveReturn::class)->handle($return, $staff, keepItems: true);
    });

    refundReturnAs($return, $this->owner)->assertConflict()->assertJsonPath('code', 'return_is_an_exchange');
});

it('needs returns.manage to choose replacements or change how a return is settled', function (): void {
    $return = requestedExchange($this->order, $this->replacement);
    $viewer = returnsStaffWith($this->store, ['returns.view']);

    returnsRequest('PUT', "{$return->public_id}/replacements", ['lines' => [['line' => $this->line, 'replacement' => $this->replacement->public_id]]], as: $viewer)->assertForbidden();
    returnsRequest('PUT', "{$return->public_id}/resolution", ['resolution' => 'refund'], as: $viewer)->assertForbidden();
});
