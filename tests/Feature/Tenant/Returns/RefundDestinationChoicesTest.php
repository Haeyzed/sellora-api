<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Privacy\PersonalDataErasure;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\OrderAnonymisation;
use App\Tenant\Returns\Enums\ReturnRefundStatus;
use App\Tenant\Returns\Events\ReturnRefundDestinationChosen;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnRefund;
use App\Tenant\Returns\Models\ReturnRefundChoice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Payments\FakeStoreCreditDestination;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18b: who chooses where a return's refund goes (section 13). The
 * customer chooses, while signed in; staff only record the customer's
 * choice, saying how they asked. With no choice it goes back to the original
 * payment. The choice is open until a refund attempt starts, and reopens
 * only once the last attempt definitely got nothing back. Choices are only
 * ever added, and staff's notes are cleared with the order's personal data.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = returnsStore();
    $this->customer = $this->store->run(static fn (): Customer => Customer::factory()->create());
    useStoreCreditDestination();
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets the customer choose where the refund goes when asking for the return, announcing it', function (): void {
    Event::fake([ReturnRefundDestinationChosen::class]);
    $order = returnableOrder([[10000, 1]], $this->customer);

    $response = customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'changed_mind',
        'refund_destination' => FakeStoreCreditDestination::KEY,
    ])->assertCreated();

    expect($response->json('data.refund_destination'))->toMatchArray(['key' => FakeStoreCreditDestination::KEY, 'label' => 'Store credit']);
    $this->store->run(function (): void {
        $choice = ReturnRefundChoice::query()->sole();

        expect([$choice->destination, $choice->chosen_by_type, $choice->chosen_by_id, $choice->note])->toBe([FakeStoreCreditDestination::KEY, 'customer', $this->customer->public_id, null]);
        Event::assertDispatchedTimes(ReturnRefundDestinationChosen::class, 1);
    });
});

it('goes back to the original payment when nobody chose', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, ['lines' => returnLinesPayload([$order->lines[0]->public_id => 1]), 'reason' => 'changed_mind'])
        ->assertCreated()
        ->assertJsonPath('data.refund_destination', ['key' => 'original_payment', 'label' => 'Back to how you paid', 'chosen_at' => null]);

    $this->store->run(static fn () => expect(ReturnRefundChoice::query()->count())->toBe(0));
});

it('refuses a destination not on offer, asking for nothing', function (string $destination, string $state): void {
    putStoreCreditDestinationIn($state);
    $order = returnableOrder([[10000, 1]], $this->customer);

    customerReturnRequest('POST', "orders/{$order->public_id}/returns", $this->customer, [
        'lines' => returnLinesPayload([$order->lines[0]->public_id => 1]),
        'reason' => 'changed_mind',
        'refund_destination' => $destination,
    ])->assertUnprocessable()->assertJsonPath('code', 'refund_destination_not_offered')->assertJsonValidationErrors('destination');

    $this->store->run(static fn () => expect([OrderReturn::query()->count(), ReturnRefundChoice::query()->count()])->toBe([0, 0]));
})->with([
    'a module switched off by the merchant' => [FakeStoreCreditDestination::KEY, 'disabled'],
    'a module locked by a downgrade' => [FakeStoreCreditDestination::KEY, 'locked'],
    'a key no destination has' => ['bank_cheque', 'enabled'],
]);

it('lets the customer change the choice until a refund attempt starts, and again once one definitely got nothing back', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    $choose = fn (string $destination) => customerReturnRequest('PUT', "returns/{$return->public_id}/refund-destination", $this->customer, ['destination' => $destination]);

    $choose(FakeStoreCreditDestination::KEY)->assertOk()->assertJsonPath('data.refund_destination.key', FakeStoreCreditDestination::KEY);
    $choose('original_payment')->assertOk()->assertJsonPath('data.refund_destination.key', 'original_payment');

    $attempt = $this->store->run(static fn (): ReturnRefund => tap((new ReturnRefund)->forceFill([
        'return_id' => $return->id, 'attempt' => 1, 'idempotency_key' => ReturnRefund::keyFor($return, 1), 'status' => ReturnRefundStatus::Started,
        'shipping' => false, 'requested_by_type' => 'staff_member', 'requested_by_id' => 'staff',
    ]))->save());

    // Payments not yet heard from: a refund may be on its way.
    $choose(FakeStoreCreditDestination::KEY)->assertConflict()->assertJsonPath('code', 'refund_destination_choice_closed');

    $this->store->run(static fn () => $attempt->forceFill(['status' => ReturnRefundStatus::Refused, 'refusal_code' => 'refund_destination_refused'])->save());
    $choose(FakeStoreCreditDestination::KEY)->assertOk()->assertJsonPath('data.refund_destination.key', FakeStoreCreditDestination::KEY);

    $this->store->run(static fn () => expect(ReturnRefundChoice::query()->orderBy('id')->pluck('destination')->all())->toBe([FakeStoreCreditDestination::KEY, 'original_payment', FakeStoreCreditDestination::KEY]));
});

it('closes the choice once the return is finished', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    customerReturnRequest('POST', "returns/{$return->public_id}/cancel", $this->customer)->assertOk();

    customerReturnRequest('PUT', "returns/{$return->public_id}/refund-destination", $this->customer, ['destination' => 'original_payment'])
        ->assertConflict()->assertJsonPath('code', 'refund_destination_choice_closed');
});

it('never lets anyone choose for another customer\'s return', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    $someoneElse = $this->store->run(static fn (): Customer => Customer::factory()->create());

    customerReturnRequest('PUT', "returns/{$return->public_id}/refund-destination", $someoneElse, ['destination' => 'original_payment'])->assertNotFound();
});

it('lets staff record the customer\'s choice only with a note on how they asked, logged and audited without the note', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    $path = "{$return->public_id}/refund-destination";

    returnsRequest('PUT', $path, ['destination' => FakeStoreCreditDestination::KEY], as: $this->owner)->assertUnprocessable()->assertJsonValidationErrors('note');
    returnsRequest('PUT', $path, ['destination' => FakeStoreCreditDestination::KEY, 'note' => 'Phone call, 3 Nov'], as: returnsStaffWith($this->store, ['returns.view']))->assertForbidden();

    returnsRequest('PUT', $path, ['destination' => FakeStoreCreditDestination::KEY, 'note' => '  Phone call, 3 Nov  '], as: $this->owner)
        ->assertOk()
        ->assertJsonPath('data.refund_destination.key', FakeStoreCreditDestination::KEY)
        ->assertJsonPath('data.refund_destination.label', 'Store credit')
        ->assertJsonPath('data.refund_destination.chosen_by', ['type' => 'staff_member', 'id' => $this->owner->public_id])
        ->assertJsonPath('data.refund_destination.note', 'Phone call, 3 Nov');

    $this->store->run(function () use ($return): void {
        $activity = Activity::query()->where('event', 'refund_destination_recorded')->sole();
        $audit = Audit::query()->where('auditable_type', 'return_refund_choice')->sole();

        expect($activity->causer?->is($this->owner))->toBeTrue()
            ->and($activity->subject?->is($return))->toBeTrue()
            ->and($activity->properties->all())->toBe(['destination' => FakeStoreCreditDestination::KEY])
            ->and($audit->new_values)->toHaveKey('destination', FakeStoreCreditDestination::KEY)
            ->and($audit->new_values)->not->toHaveKey('note');
    });
    // The customer sees where it goes, never staff's note.
    expect(customerReturnRequest('GET', "returns/{$return->public_id}", $this->customer)->json('data.refund_destination'))->not->toHaveKey('note');
});

it('lets staff record only the original payment for a guest\'s return', function (): void {
    $return = requestedReturn(returnableOrder([[10000, 1]]));
    $path = "{$return->public_id}/refund-destination";

    returnsRequest('PUT', $path, ['destination' => FakeStoreCreditDestination::KEY, 'note' => 'Asked by email'], as: $this->owner)
        ->assertUnprocessable()->assertJsonPath('code', 'refund_destination_not_offered');
    returnsRequest('PUT', $path, ['destination' => 'original_payment', 'note' => 'Asked by email'], as: $this->owner)
        ->assertOk()->assertJsonPath('data.refund_destination.key', 'original_payment');
});

it('keeps every choice in the customer\'s data export, and clears staff\'s notes when the order is anonymised', function (): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    customerReturnRequest('PUT', "returns/{$return->public_id}/refund-destination", $this->customer, ['destination' => FakeStoreCreditDestination::KEY])->assertOk();
    returnsRequest('PUT', "{$return->public_id}/refund-destination", ['destination' => 'original_payment', 'note' => 'Phone call from Ada, 0803 000 0000'], as: $this->owner)->assertOk();

    $export = $this->store->run(fn (): array => app(PersonalDataRegistry::class)->export(CustomerDataSubject::of($this->customer)));
    expect(array_map(static fn (array $choice): array => [$choice['destination'], $choice['chosen_by'], $choice['note']], $export['returns'][0]['refund_destination_choices']))
        ->toBe([[FakeStoreCreditDestination::KEY, 'customer', null], ['original_payment', 'staff', 'Phone call from Ada, 0803 000 0000']]);

    $this->store->run(static fn () => app(PersonalDataErasure::class)->run(static fn () => app(OrderAnonymisation::class)->anonymise(Order::query()->sole())));

    $this->store->run(static function (): void {
        $choices = ReturnRefundChoice::query()->orderBy('id')->get();

        expect($choices->pluck('destination')->all())->toBe([FakeStoreCreditDestination::KEY, 'original_payment'])
            ->and($choices->pluck('note')->all())->toBe([null, null])
            ->and($choices->every(static fn (ReturnRefundChoice $choice): bool => $choice->anonymised_at !== null || $choice->note === null))->toBeTrue()
            ->and($choices->last()?->anonymised_at)->not->toBeNull();
    });
});

it('refuses any change to a choice, outside erasure, and a staff choice without a note', function (string $statement): void {
    $order = returnableOrder([[10000, 1]], $this->customer);
    $return = requestedReturn($order);
    returnsRequest('PUT', "{$return->public_id}/refund-destination", ['destination' => 'original_payment', 'note' => 'Phone call'], as: $this->owner)->assertOk();

    $this->store->run(static fn () => expect(static fn () => DB::statement($statement))->toThrow(QueryException::class));
})->with([
    'changing where it goes' => ["update return_refund_choices set destination = 'store_credit'"],
    'clearing the note' => ['update return_refund_choices set note = null, anonymised_at = now()'],
    'deleting it' => ['delete from return_refund_choices'],
    'a staff choice without a note' => ["insert into return_refund_choices (return_id, destination, chosen_by_type, chosen_by_id, created_at) select return_id, 'original_payment', 'staff_member', 'x', now() from return_refund_choices"],
]);
