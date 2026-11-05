<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: the buyer's reason only claims the store was at fault (damaged,
 * the wrong item, faulty, not as described). Staff confirm it when they
 * inspect the items, or approve the return without them coming back:
 * saying nothing confirms the claim, deciding otherwise needs a reason.
 * A refund gives shipping back by default when the fault is confirmed.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->order = returnableOrder([[10000, 1]], paidByHand: true);
    $this->inspection = ['lines' => [['line' => $this->order->lines->sole()->public_id, 'restocked' => 1, 'written_off' => 0]]];
});

afterEach(function (): void {
    deleteAllStores();
});

it('confirms what the reason claims when staff inspect the items and say nothing', function (ReturnReason $reason, bool $claimed): void {
    $return = receivedReturn($this->order, reason: $reason);

    returnsRequest('POST', "{$return->public_id}/inspect", $this->inspection, as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.stores_fault', $claimed)
        ->assertJsonPath('data.fault_overruled_reason', null);
})->with([
    'damaged' => [ReturnReason::Damaged, true],
    'faulty' => [ReturnReason::Faulty, true],
    'not as described' => [ReturnReason::NotAsDescribed, true],
    'changed their mind' => [ReturnReason::ChangedMind, false],
]);

it('lets staff overrule the claim only with a reason, which is kept', function (): void {
    $return = receivedReturn($this->order, reason: ReturnReason::Damaged);

    returnsRequest('POST', "{$return->public_id}/inspect", [...$this->inspection, 'stores_fault' => false], as: $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'return_fault_reason_required')
        ->assertJsonValidationErrorFor('fault_overruled_reason');

    returnsRequest('POST', "{$return->public_id}/inspect", [...$this->inspection, 'stores_fault' => false, 'fault_overruled_reason' => 'Dropped by the buyer, as the photo shows'], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.stores_fault', false)
        ->assertJsonPath('data.fault_overruled_reason', 'Dropped by the buyer, as the photo shows');
});

it('drops a reason given when nothing is overruled', function (): void {
    $return = receivedReturn($this->order, reason: ReturnReason::Faulty);

    returnsRequest('POST', "{$return->public_id}/inspect", [...$this->inspection, 'stores_fault' => true, 'fault_overruled_reason' => 'Agreed'], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.stores_fault', true)
        ->assertJsonPath('data.fault_overruled_reason', null);
});

it('confirms the fault on approval when the buyer keeps the items, and only then', function (): void {
    $kept = requestedReturn($this->order, reason: ReturnReason::ChangedMind);
    $other = requestedReturn(returnableOrder([[10000, 1]], paidByHand: true), reason: ReturnReason::Damaged);

    returnsRequest('POST', "{$kept->public_id}/approve", ['keep_items' => true, 'stores_fault' => true], as: $this->owner)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'return_fault_reason_required');
    returnsRequest('POST', "{$kept->public_id}/approve", ['keep_items' => true, 'stores_fault' => true, 'fault_overruled_reason' => 'Our label was wrong'], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.stores_fault', true)
        ->assertJsonPath('data.fault_overruled_reason', 'Our label was wrong');

    // The items come back: the fault waits for the inspection.
    returnsRequest('POST', "{$other->public_id}/approve", ['stores_fault' => false], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.stores_fault', null);
});

it('gives shipping back by default only when the fault is confirmed', function (bool $storesFault, bool $shipping): void {
    $return = receivedReturn($this->order, reason: ReturnReason::Damaged);
    returnsRequest('POST', "{$return->public_id}/inspect", [...$this->inspection, 'stores_fault' => $storesFault, 'fault_overruled_reason' => $storesFault ? null : 'Damaged by the buyer'], as: $this->owner)->assertOk();

    refundReturnAs($return, $this->owner)->assertOk();

    expect($this->store->run(static fn (): bool => ReturnRefund::query()->where('return_id', $return->id)->sole()->shipping))->toBe($shipping)
        ->and($this->store->run(static fn (): ?bool => OrderReturn::query()->findOrFail($return->id)->stores_fault))->toBe($storesFault);
})->with([
    'confirmed' => [true, true],
    'overruled' => [false, false],
]);
