<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Tenant\Orders\Actions\CancelOrder;
use App\Tenant\Orders\Actions\PlaceOrder;
use App\Tenant\Orders\Data\OrderSource;
use App\Tenant\Orders\Enums\CancellationReason;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Models\OrderReturn;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18c: an order comes from exactly one place, a checkout or a return it
 * replaces in an exchange. Placing from the same return again returns its
 * replacement, until that one is cancelled; the database keeps one live
 * replacement per return, and the return an order replaces never changes.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    returnsStore();
    $this->return = requestedReturn(returnableOrder());
});

afterEach(function (): void {
    deleteAllStores();
});

it('places a replacement from the return it replaces, with no checkout, once', function (): void {
    $return = $this->return;

    [$first, $again, $count] = $this->store->run(static function () use ($return): array {
        $draft = orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(name: 'Replacement'), source: OrderSource::replacingReturn($return->id, $return->public_id));

        return [app(PlaceOrder::class)->handle($draft), app(PlaceOrder::class)->handle($draft), Order::query()->where('replaces_return_id', $return->id)->count()];
    });

    expect($first->isNew)->toBeTrue()
        ->and($again->isNew)->toBeFalse()
        ->and($again->order->id)->toBe($first->order->id)
        ->and($first->order->replaces_return_id)->toBe($return->id)
        ->and($first->order->checkout_id)->toBeNull()
        ->and($count)->toBe(1);
});

it('waits for another placement of the same return, then finds its replacement', function (): void {
    $return = $this->return;

    $this->store->run(static function () use ($return): void {
        $draft = orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(name: 'Replacement'), source: OrderSource::replacingReturn($return->id, $return->public_id));
        $otherRequest = SecondConnection::open()->holdAdvisoryLock($draft->source->lockKey());

        expect($otherRequest->blocks(static fn () => app(PlaceOrder::class)->handle($draft)))->toBeTrue();

        $otherRequest->commit();

        expect(app(PlaceOrder::class)->handle($draft)->isNew)->toBeTrue()
            ->and(app(PlaceOrder::class)->handle($draft)->isNew)->toBeFalse();
    });
});

it('frees the return for a new replacement once its replacement is cancelled', function (): void {
    $return = $this->return;

    [$first, $second] = $this->store->run(static function () use ($return): array {
        $source = OrderSource::replacingReturn($return->id, $return->public_id);
        $first = app(PlaceOrder::class)->handle(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(name: 'First'), source: $source))->order;
        app(CancelOrder::class)->handle($first, CancellationReason::PaymentExpired, null);
        $second = app(PlaceOrder::class)->handle(orderDraft([[cartVariant(10000, stock: 5), 1]], orderShippingMethod(name: 'Second'), source: $source))->order;

        return [$first->refresh(), $second];
    });

    expect($second->id)->not->toBe($first->id)
        ->and($first->status->value)->toBe('cancelled')
        ->and($second->replaces_return_id)->toBe($return->id);
});

it('keeps one source per order, and one live replacement per return, in the database', function (Closure $insert, string $message): void {
    $return = $this->return;

    expect(fn () => $this->store->run(static fn () => $insert($return)))->toThrow(QueryException::class, $message);
})->with([
    'neither source' => [fn (OrderReturn $return) => rawOrder(['checkout_id' => null]), 'orders_placed_from_one_source'],
    'both sources' => [fn (OrderReturn $return) => rawOrder(['replaces_return_id' => $return->id]), 'orders_placed_from_one_source'],
    'two live replacements' => [function (OrderReturn $return): void {
        rawOrder(['checkout_id' => null, 'replaces_return_id' => $return->id]);
        rawOrder(['checkout_id' => null, 'replaces_return_id' => $return->id]);
    }, 'orders_one_live_replacement_per_return'],
]);

it('never changes the return an order replaces', function (): void {
    $return = $this->return;
    $other = requestedReturn(returnableOrder());

    expect(fn () => $this->store->run(static function () use ($return, $other): void {
        $order = rawOrder(['checkout_id' => null, 'replaces_return_id' => $return->id]);
        DB::table('orders')->where('id', $order)->update(['replaces_return_id' => $other->id]);
    }))->toThrow(QueryException::class, 'never change');
});
