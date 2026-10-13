<?php

declare(strict_types=1);

use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\CommitReservation;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Actions\ReleaseExpiredReservations;
use App\Tenant\Inventory\Actions\ReleaseReservation;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Actions\TransferReservation;
use App\Tenant\Inventory\CheckoutHold;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Enums\ReservationStatus;
use App\Tenant\Inventory\Exceptions\InsufficientStockException;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Inventory\SettledReservationRetention;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13: stock is reserved for checkouts and orders, all lines or none,
 * so two buyers can never both get the last unit. Reserving again for the
 * same holder changes its reservation; releasing, committing and expiring
 * act only on active reservations, so they are safe to run twice.
 * Backorders are per variant: turning them off keeps existing backordered
 * reservations and refuses new ones. Untracked variants are always available.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->store = createStore('reservation-store');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    deleteAllStores();
});

it('never lets two buyers both get the last unit', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(1);
        $reserve = static fn (): array => app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 1)], CheckoutHold::endsAt());

        // The other buyer holds the level and has reserved the last unit, but not yet committed.
        $otherBuyer = SecondConnection::open()->holdRowLock(stockItemOf($variant));
        $otherBuyer->run(static function () use ($variant): void {
            $connection = DB::connection(SecondConnection::NAME);
            $connection->table('stock_items')->where('product_variant_id', $variant->id)->update(['reserved' => 1]);
            $connection->table('stock_reservations')->insert([
                'public_id' => Str::lower((string) Str::ulid()), 'product_variant_id' => $variant->id, 'stock_location_id' => StockLocation::query()->sole()->id,
                'holder_type' => 'checkout', 'holder_id' => 'other-buyer', 'quantity' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        expect($otherBuyer->blocks($reserve))->toBeTrue();

        $otherBuyer->commit();

        expect($reserve)->toThrow(InsufficientStockException::class)
            ->and(stockItemOf($variant)->reserved)->toBe(1)
            ->and(StockReservation::query()->where('holder_id', '<>', 'other-buyer')->count())->toBe(0);
    });
});

it('reserves every line or none, saying which fell short', function (): void {
    $this->store->run(static function (): void {
        $plenty = variantWithStockOnHand(10, 'MANY-1');
        $scarce = variantWithStockOnHand(1, 'FEW-1');

        try {
            app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($plenty, 2), new StockLine($scarce, 3)]);
            test()->fail('Expected an insufficient stock error.');
        } catch (InsufficientStockException $exception) {
            expect($exception->shortfalls)->toBe([['variant_id' => $scarce->public_id, 'requested' => 3, 'available' => 1]]);
        }

        expect(stockItemOf($plenty)->reserved)->toBe(0)
            ->and(StockReservation::query()->count())->toBe(0);
    });
});

it('changes a holder\'s reservation when it reserves again, instead of adding another', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(10);
        $checkout = newStockHolder();
        $reserve = static fn (int $quantity): array => app(ReserveStock::class)->handle($checkout, [new StockLine($variant, $quantity)], CheckoutHold::endsAt());

        $reserve(2);
        $reserve(2);
        expect(StockReservation::query()->count())->toBe(1)->and(stockItemOf($variant)->reserved)->toBe(2);

        $reserve(5);
        $reserve(1);
        expect(StockReservation::query()->sole()->quantity)->toBe(1)
            ->and(stockItemOf($variant)->reserved)->toBe(1)
            ->and(StockMovement::query()->where('type', 'reserved')->orderBy('id')->pluck('reserved_delta')->all())->toBe([2, 3, -4]);
    });
});

it('always lets untracked variants be bought, holding nothing', function (): void {
    $this->store->run(static function (): void {
        $madeToOrder = variantWithStockOnHand(0, 'MADE-1');
        app(ChangeInventorySettings::class)->handle($madeToOrder, new InventorySettingsData(tracksStock: false));

        expect(app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($madeToOrder, 50)]))->toBe([])
            ->and(StockReservation::query()->count())->toBe(0)
            ->and(StockItem::query()->count())->toBe(0);
    });
});

it('lets a variant that allows backorders be reserved beyond its stock, and keeps those reservations when backorders are turned off', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(2);
        app(ChangeInventorySettings::class)->handle($variant, new InventorySettingsData(allowsBackorder: true));
        $early = newStockHolder();

        [$reservation] = app(ReserveStock::class)->handle($early, [new StockLine($variant, 5)]);
        expect($reservation->backordered_quantity)->toBe(3)
            ->and(stockItemOf($variant)->available())->toBe(-3);

        app(ChangeInventorySettings::class)->handle($variant, new InventorySettingsData(allowsBackorder: false));

        // Reserving the same again changes nothing and is still allowed; asking for more, or a new buyer, is refused.
        app(ReserveStock::class)->handle($early, [new StockLine($variant, 5)]);
        expect(StockReservation::query()->sole()->quantity)->toBe(5)
            ->and(static fn () => app(ReserveStock::class)->handle($early, [new StockLine($variant, 6)]))->toThrow(InsufficientStockException::class)
            ->and(static fn () => app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 1)]))->toThrow(InsufficientStockException::class)
            ->and(StockReservation::query()->sole()->backordered_quantity)->toBe(3);
    });
});

it('gives back a holder\'s stock, safely twice', function (): void {
    $this->store->run(static function (): void {
        $first = variantWithStockOnHand(10, 'A-1');
        $second = variantWithStockOnHand(10, 'B-1');
        $checkout = newStockHolder();
        app(ReserveStock::class)->handle($checkout, [new StockLine($first, 2), new StockLine($second, 3)]);

        expect(app(ReleaseReservation::class)->handle($checkout, [$first]))->toBe(1)
            ->and(stockItemOf($first)->reserved)->toBe(0)
            ->and(stockItemOf($second)->reserved)->toBe(3)
            ->and(app(ReleaseReservation::class)->handle($checkout))->toBe(1)
            ->and(app(ReleaseReservation::class)->handle($checkout))->toBe(0)
            ->and(stockItemOf($second)->reserved)->toBe(0)
            ->and(StockReservation::query()->pluck('status')->unique()->values()->all())->toBe([ReservationStatus::Released]);
    });
});

it('hands a checkout\'s stock to its order with no expiry, safely twice', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(5);
        $checkout = newStockHolder();
        $order = newStockHolder('order');
        app(ReserveStock::class)->handle($checkout, [new StockLine($variant, 2)], CheckoutHold::endsAt());

        expect(app(TransferReservation::class)->handle($checkout, $order))->toBe(1)
            ->and(app(TransferReservation::class)->handle($checkout, $order))->toBe(0);

        $reservation = StockReservation::query()->sole();
        expect($reservation->holder_type)->toBe('order')
            ->and($reservation->holder_id)->toBe($order->id)
            ->and($reservation->expires_at)->toBeNull()
            ->and(stockItemOf($variant)->reserved)->toBe(2);

        $another = newStockHolder();
        app(ReserveStock::class)->handle($another, [new StockLine($variant, 1)]);
        expect(static fn () => app(TransferReservation::class)->handle($another, $order))->toThrow(LogicException::class);
    });
});

it('takes an order\'s stock off the shelf at fulfilment, only when it is all there, safely twice', function (): void {
    $this->store->run(static function (): void {
        $inStock = variantWithStockOnHand(5, 'IN-1');
        $backordered = variantWithStockOnHand(1, 'BACK-1');
        app(ChangeInventorySettings::class)->handle($backordered, new InventorySettingsData(allowsBackorder: true));
        $order = newStockHolder('order');
        app(ReserveStock::class)->handle($order, [new StockLine($inStock, 2), new StockLine($backordered, 3)]);

        expect(static fn () => app(CommitReservation::class)->handle($order))->toThrow(InsufficientStockException::class)
            ->and(stockItemOf($inStock)->on_hand)->toBe(5);

        app(ReceiveStock::class)->handle($backordered, StockLocation::query()->sole(), 2);

        expect(app(CommitReservation::class)->handle($order))->toBe(2)
            ->and(app(CommitReservation::class)->handle($order))->toBe(0)
            ->and([stockItemOf($inStock)->on_hand, stockItemOf($inStock)->reserved])->toBe([3, 0])
            ->and([stockItemOf($backordered)->on_hand, stockItemOf($backordered)->reserved])->toBe([0, 0]);
    });
});

it('gives back checkout holds that ran out, and never one settled meanwhile', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(5);
        app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 2)], CheckoutHold::endsAt());
        app(ReserveStock::class)->handle(newStockHolder('order'), [new StockLine($variant, 1)]);

        CarbonImmutable::setTestNow(now()->addMinutes(14));
        expect(app(ReleaseExpiredReservations::class)->handle())->toBe(0);

        CarbonImmutable::setTestNow(now()->addMinutes(2));
        expect(app(ReleaseExpiredReservations::class)->handle())->toBe(1)
            ->and(app(ReleaseExpiredReservations::class)->handle())->toBe(0)
            ->and(stockItemOf($variant)->reserved)->toBe(1)
            ->and(StockMovement::query()->where('type', 'expired')->sole()->reserved_delta)->toBe(-2);
    });
});

it('never gives back an expired hold that another request is settling at the same moment', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(5);
        $checkout = newStockHolder();
        app(ReserveStock::class)->handle($checkout, [new StockLine($variant, 2)], CheckoutHold::endsAt());
        CarbonImmutable::setTestNow(now()->addMinutes(20));
        $sweep = static fn (): int => app(ReleaseExpiredReservations::class)->handle();

        // The other request holds the level and has just released the hold itself, but not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(stockItemOf($variant));
        $otherRequest->run(static function () use ($variant): void {
            $connection = DB::connection(SecondConnection::NAME);
            $connection->table('stock_items')->where('product_variant_id', $variant->id)->update(['reserved' => 0]);
            $connection->table('stock_reservations')->update(['status' => 'released', 'settled_at' => now()]);
        });

        expect($otherRequest->blocks($sweep))->toBeTrue();

        $otherRequest->commit();

        expect($sweep())->toBe(0)
            ->and(stockItemOf($variant)->reserved)->toBe(0);
    });
});

it('leaves alone a hold picked as expired that was given back before the sweep reached it', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(5);
        $checkout = newStockHolder();
        app(ReserveStock::class)->handle($checkout, [new StockLine($variant, 2)], CheckoutHold::endsAt());
        CarbonImmutable::setTestNow(now()->addMinutes(20));

        $picked = StockReservation::query()->sole();
        app(ReleaseReservation::class)->handle($checkout);

        expect(app(ReleaseExpiredReservations::class)->releaseIfStillExpired($picked))->toBeFalse()
            ->and(stockItemOf($variant)->reserved)->toBe(0)
            ->and(StockReservation::query()->sole()->status)->toBe(ReservationStatus::Released);
    });
});

it('gives back expired holds on a variant before reserving it, so they never block a buyer', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(1);
        app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 1)], CheckoutHold::endsAt());
        CarbonImmutable::setTestNow(now()->addMinutes(16));

        expect(app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 1)], CheckoutHold::endsAt()))->toHaveCount(1)
            ->and(StockReservation::query()->where('status', 'expired')->count())->toBe(1)
            ->and(stockItemOf($variant)->reserved)->toBe(1);
    });
});

it('holds checkouts for 15 minutes unless configured otherwise, and sweeps expired holds on a schedule', function (): void {
    CarbonImmutable::setTestNow('2026-10-13 10:00:00');

    expect(CheckoutHold::endsAt()->toIso8601String())->toBe(CarbonImmutable::parse('2026-10-13 10:15:00')->toIso8601String());

    $commands = array_map(static fn ($event): string => (string) $event->command, app(Schedule::class)->events());
    expect(array_filter($commands, static fn (string $command): bool => str_contains($command, 'inventory:release-expired-reservations')))->toHaveCount(1);
});

it('reports a reserved count that doesn\'t match the active reservations', function (): void {
    $this->store->run(static function (): void {
        app(ReserveStock::class)->handle(newStockHolder(), [new StockLine(variantWithStockOnHand(5), 2)]);
    });
    expect(Artisan::call('inventory:reconcile'))->toBe(0);

    $this->store->run(static fn () => StockReservation::query()->update(['quantity' => 3]));

    expect(Artisan::call('inventory:reconcile'))->toBe(1)
        ->and(Artisan::output())->toContain('active reservations 3');
});

it('deletes ended reservations after the retention period, never active ones', function (): void {
    $this->store->run(static function (): void {
        $variant = variantWithStockOnHand(5);
        $ended = newStockHolder();
        app(ReserveStock::class)->handle($ended, [new StockLine($variant, 1)]);
        app(ReleaseReservation::class)->handle($ended);
        app(ReserveStock::class)->handle(newStockHolder(), [new StockLine($variant, 1)]);

        expect(app(SettledReservationRetention::class)->purgeOlderThan(CarbonImmutable::now()->addDay()))->toBe(1)
            ->and(StockReservation::query()->pluck('status')->all())->toBe([ReservationStatus::Active])
            ->and(StockMovement::query()->count())->toBe(4);
    });
});
