<?php

declare(strict_types=1);

use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.4: the database refuses a return whose status, times and reasons
 * don't agree, whatever the code does: each check with its NULLs in place
 * (section 13). A return's lines add up once inspected, its history is only
 * ever added to, and a store has one row of return settings at most.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    [$this->order, $this->line, $this->location] = $this->store->run(static function (): array {
        $order = unpaidOrder();

        return [$order, $order->lines()->sole(), StockLocation::query()->sole()];
    });
    $this->insertReturn = function (array $changes): int {
        $now = now();

        return (int) DB::table('returns')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'order_id' => $this->order->id,
            'sequence' => DB::table('returns')->where('order_id', $this->order->id)->count() + 1,
            'status' => 'requested',
            'resolution' => 'refund',
            'reason' => 'damaged',
            'is_test' => false,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            ...$changes,
        ]);
    };
});

afterEach(function (): void {
    deleteAllStores();
});

it('accepts a return whose status, times and reasons agree', function (): void {
    $this->store->run(function (): void {
        $now = now();

        foreach ([
            [],
            ['status' => 'approved', 'approved_at' => $now],
            ['status' => 'approved', 'approved_at' => $now, 'keep_items' => true],
            ['status' => 'received', 'approved_at' => $now, 'received_at' => $now],
            ['status' => 'inspected', 'approved_at' => $now, 'received_at' => $now, 'inspected_at' => $now, 'stock_location_id' => $this->location->id],
            ['status' => 'completed', 'approved_at' => $now, 'received_at' => $now, 'inspected_at' => $now, 'stock_location_id' => $this->location->id, 'completed_at' => $now],
            ['status' => 'completed', 'approved_at' => $now, 'keep_items' => true, 'completed_at' => $now],
            ['status' => 'rejected', 'rejected_at' => $now, 'rejection_reason' => 'Bought from another store'],
            ['status' => 'rejected', 'approved_at' => $now, 'received_at' => $now, 'inspected_at' => $now, 'stock_location_id' => $this->location->id, 'rejected_at' => $now, 'rejection_reason' => 'Damaged by the buyer'],
            ['status' => 'cancelled', 'cancelled_at' => $now],
            ['status' => 'cancelled', 'approved_at' => $now, 'cancelled_at' => $now],
            ['requested_by_type' => 'customer', 'requested_by_id' => (string) Str::ulid()],
        ] as $changes) {
            ($this->insertReturn)($changes);
        }

        expect(DB::table('returns')->count())->toBe(12);
    });
});

it('refuses a return whose status, times and reasons don\'t agree', function (array $changes): void {
    $this->store->run(function () use ($changes): void {
        $now = now()->toIso8601String();
        $changes = array_map(static fn (mixed $value): mixed => $value === 'now' ? $now : ($value === 'location' ? test()->location->id : $value), $changes);

        expect(fn () => ($this->insertReturn)($changes))->toThrow(QueryException::class);
    });
})->with([
    'an unknown status' => [['status' => 'lost']],
    'an unknown reason' => [['reason' => 'bored']],
    'an exchange, before Step 18b' => [['resolution' => 'exchange']],
    'a sequence of 0' => [['sequence' => 0]],
    'who asked, half given' => [['requested_by_type' => 'customer']],
    'approved without when' => [['status' => 'approved']],
    'received without when' => [['status' => 'received', 'approved_at' => 'now']],
    'received but never approved' => [['status' => 'received', 'received_at' => 'now']],
    'inspected without when' => [['status' => 'inspected', 'approved_at' => 'now', 'received_at' => 'now', 'stock_location_id' => 'location']],
    'inspected without a location' => [['status' => 'inspected', 'approved_at' => 'now', 'received_at' => 'now', 'inspected_at' => 'now']],
    'inspected but never received' => [['status' => 'inspected', 'approved_at' => 'now', 'inspected_at' => 'now', 'stock_location_id' => 'location']],
    'kept items that came back' => [['status' => 'received', 'approved_at' => 'now', 'received_at' => 'now', 'keep_items' => true]],
    'completed without when' => [['status' => 'completed', 'approved_at' => 'now', 'keep_items' => true]],
    'completed without inspection or kept items' => [['status' => 'completed', 'approved_at' => 'now', 'completed_at' => 'now']],
    'a completion time while open' => [['completed_at' => 'now']],
    'rejected without a reason' => [['status' => 'rejected', 'rejected_at' => 'now']],
    'rejected without when' => [['status' => 'rejected', 'rejection_reason' => 'No']],
    'a rejection time while open' => [['rejected_at' => 'now', 'rejection_reason' => 'No']],
    'cancelled without when' => [['status' => 'cancelled']],
    'cancelled after the items arrived' => [['status' => 'cancelled', 'approved_at' => 'now', 'received_at' => 'now', 'cancelled_at' => 'now']],
    'a cancellation time while open' => [['cancelled_at' => 'now']],
]);

it('numbers returns once per order', function (): void {
    $this->store->run(function (): void {
        ($this->insertReturn)(['sequence' => 1]);

        expect(fn () => ($this->insertReturn)(['sequence' => 1]))->toThrow(QueryException::class);
    });
});

it('refuses return lines that don\'t add up once inspected', function (array $line): void {
    $this->store->run(function () use ($line): void {
        $return = ($this->insertReturn)([]);
        $insert = fn (array $line) => DB::table('return_lines')->insert(['return_id' => $return, 'order_line_id' => $this->line->id, 'created_at' => now(), 'updated_at' => now(), ...$line]);

        expect(fn () => $insert($line))->toThrow(QueryException::class);
    });
})->with([
    'no units' => [['quantity' => 0]],
    'restocked without written off' => [['quantity' => 2, 'restocked_quantity' => 2]],
    'written off without restocked' => [['quantity' => 2, 'written_off_quantity' => 2]],
    'fewer than came back' => [['quantity' => 2, 'restocked_quantity' => 1, 'written_off_quantity' => 0]],
    'more than came back' => [['quantity' => 2, 'restocked_quantity' => 2, 'written_off_quantity' => 1]],
    'a negative count' => [['quantity' => 2, 'restocked_quantity' => 3, 'written_off_quantity' => -1]],
]);

it('accepts return lines before and after inspection, once per order line', function (): void {
    $this->store->run(function (): void {
        $insert = fn (int $return, array $line) => DB::table('return_lines')->insert(['return_id' => $return, 'order_line_id' => $this->line->id, 'created_at' => now(), 'updated_at' => now(), ...$line]);
        $first = ($this->insertReturn)([]);
        $insert($first, ['quantity' => 1]);
        $insert(($this->insertReturn)([]), ['quantity' => 2, 'restocked_quantity' => 1, 'written_off_quantity' => 1]);

        expect(fn () => $insert($first, ['quantity' => 1]))->toThrow(QueryException::class);
    });
});

it('only ever adds to a return\'s history', function (): void {
    $this->store->run(function (): void {
        $return = ($this->insertReturn)([]);
        DB::table('return_status_changes')->insert(['return_id' => $return, 'from_status' => null, 'to_status' => 'requested', 'created_at' => now()]);

        expect(fn () => DB::table('return_status_changes')->update(['to_status' => 'approved']))->toThrow(QueryException::class)
            ->and(fn () => DB::table('return_status_changes')->delete())->toThrow(QueryException::class)
            ->and(fn () => DB::statement('truncate return_status_changes'))->toThrow(QueryException::class)
            ->and(fn () => DB::table('return_status_changes')->insert(['return_id' => $return, 'to_status' => 'approved', 'changed_by_type' => 'staff_member', 'created_at' => now()]))->toThrow(QueryException::class);
    });
});

it('keeps one row of return settings, with a window of up to a year', function (): void {
    $this->store->run(static function (): void {
        $insert = static fn (array $row) => DB::table('return_settings')->insert(['window_days' => 14, 'customer_requests' => true, 'photos_required' => false, ...$row]);

        expect(fn () => $insert(['id' => 2]))->toThrow(QueryException::class)
            ->and(fn () => $insert(['id' => 1, 'window_days' => 366]))->toThrow(QueryException::class)
            ->and(fn () => $insert(['id' => 1, 'window_days' => -1]))->toThrow(QueryException::class);

        $insert(['id' => 1]);

        expect(Order::query()->count())->toBe(1);
    });
});
