<?php

declare(strict_types=1);

use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: what the database keeps true about exchanges, whatever the code
 * does. Every line of an exchange names its replacement and a refund's
 * name none, checked when the transaction commits; a return awaits an
 * exchange only once inspected or kept; staff's word on the store's fault
 * comes with a reason exactly when it overrules the buyer's, and is there
 * once the return was inspected or kept; a confirmed exchange's amounts
 * add up, the store keeps something only when it was at fault, and a return
 * has one exchange that isn't released; a refund attempt by amount gives
 * back nothing else.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('first-store', ['country_code' => 'NG']);
    [$this->order, $this->line, $this->location, $this->variant] = $this->store->run(static function (): array {
        $order = unpaidOrder();

        return [$order, $order->lines()->sole(), StockLocation::query()->sole(), cartVariant(5000)];
    });
    $this->insertReturn = function (array $changes = []): int {
        $now = now();

        return (int) DB::table('returns')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'order_id' => $this->order->id,
            'sequence' => DB::table('returns')->where('order_id', $this->order->id)->count() + 1,
            'status' => 'requested',
            'resolution' => 'exchange',
            'reason' => 'damaged',
            'is_test' => false,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            ...$changes,
        ]);
    };
    $this->insertLine = function (int $returnId, ?int $replacement): void {
        DB::table('return_lines')->insert(['return_id' => $returnId, 'order_line_id' => $this->line->id, 'replacement_variant_id' => $replacement, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
    };
    $this->insertExchange = function (int $returnId, array $changes = []): void {
        $now = now();
        $replacement = rawOrder();

        DB::table('return_exchanges')->insert([
            'public_id' => (string) Str::ulid(), 'return_id' => $returnId, 'order_id' => $replacement, 'status' => 'pending', 'stores_fault' => true, 'shipping_charged' => false,
            'currency' => 'NGN', 'returned_value_amount' => 10000, 'credit_amount' => 6000, 'difference_amount' => 4000, 'kept_amount' => 0,
            'confirmed_by_type' => 'staff_member', 'confirmed_by_id' => (string) Str::ulid(), 'created_at' => $now, 'updated_at' => $now,
            ...$changes,
        ]);
    };
});

afterEach(function (): void {
    deleteAllStores();
});

it('keeps every exchange line\'s replacement in step with its return, when the transaction commits', function (Closure $write): void {
    expect(fn () => $this->store->run(fn () => DB::transaction(fn () => $write($this))))->toThrow(PDOException::class, 'names its replacement');
})->with([
    'an exchange line with no replacement' => [fn ($test) => ($test->insertLine)(($test->insertReturn)(), null)],
    'a refund line with a replacement' => [fn ($test) => ($test->insertLine)(($test->insertReturn)(['resolution' => 'refund']), $test->variant->id)],
    'a refund made an exchange without replacements' => [function ($test): void {
        $return = ($test->insertReturn)(['resolution' => 'refund']);
        ($test->insertLine)($return, null);
        DB::table('returns')->where('id', $return)->update(['resolution' => 'exchange']);
    }],
]);

it('checks the lines again when only a return\'s resolution changes', function (): void {
    expect(fn () => $this->store->run(function (): void {
        $return = DB::transaction(function (): int {
            $return = ($this->insertReturn)(['resolution' => 'refund']);
            ($this->insertLine)($return, null);

            return $return;
        });

        // A transaction of its own, which touches no line.
        DB::transaction(static fn () => DB::table('returns')->where('id', $return)->update(['resolution' => 'exchange']));
    }))->toThrow(PDOException::class, 'names its replacement');
});

it('accepts exchanges whose lines all name their replacement, and refunds whose lines name none', function (): void {
    $count = $this->store->run(function (): int {
        DB::transaction(function (): void {
            ($this->insertLine)(($this->insertReturn)(), $this->variant->id);
            ($this->insertLine)(($this->insertReturn)(['resolution' => 'refund']), null);
        });

        return DB::table('return_lines')->count();
    });

    expect($count)->toBe(2);
});

it('refuses a return whose exchange or fault doesn\'t agree', function (array $changes, string $constraint): void {
    $now = now()->toIso8601String();
    $changes = array_map(static fn (mixed $value): mixed => $value === 'now' ? $now : ($value === 'location' ? test()->location->id : $value), $changes);

    expect(fn () => $this->store->run(fn () => ($this->insertReturn)($changes)))->toThrow(QueryException::class, $constraint);
})->with([
    'awaiting an exchange before inspection' => [['status' => 'awaiting_exchange', 'approved_at' => 'now'], 'returns_awaiting_exchange'],
    'a refund awaiting an exchange' => [['resolution' => 'refund', 'status' => 'awaiting_exchange', 'approved_at' => 'now', 'keep_items' => true, 'stores_fault' => true], 'returns_awaiting_exchange'],
    'inspected with no word on the fault' => [['status' => 'inspected', 'approved_at' => 'now', 'received_at' => 'now', 'inspected_at' => 'now', 'stock_location_id' => 'location'], 'returns_fault_confirmed'],
    'kept with no word on the fault' => [['status' => 'approved', 'approved_at' => 'now', 'keep_items' => true], 'returns_fault_confirmed'],
    'overruled without a reason' => [['status' => 'approved', 'approved_at' => 'now', 'keep_items' => true, 'stores_fault' => false], 'returns_fault_overruled'],
    'a reason with nothing overruled' => [['status' => 'approved', 'approved_at' => 'now', 'keep_items' => true, 'stores_fault' => true, 'fault_overruled_reason' => 'Why not'], 'returns_fault_overruled'],
    'a faulty item claimed and not confirmed, without a reason' => [['reason' => 'faulty', 'status' => 'approved', 'approved_at' => 'now', 'keep_items' => true, 'stores_fault' => false], 'returns_fault_overruled'],
]);

it('keeps a confirmed exchange\'s amounts adding up, and one exchange per return that isn\'t released', function (array $changes, string $constraint): void {
    expect(fn () => $this->store->run(function () use ($changes): void {
        $return = ($this->insertReturn)();
        ($this->insertExchange)($return, $changes);

        if ($changes === []) {
            ($this->insertExchange)($return);
        }
    }))->toThrow(QueryException::class, $constraint);
})->with([
    'amounts that don\'t add up' => [['credit_amount' => 7000], 'return_exchanges_amounts'],
    'a negative difference' => [['credit_amount' => 11000, 'difference_amount' => -1000], 'return_exchanges_amounts'],
    'something kept with no fault' => [['stores_fault' => false, 'difference_amount' => 0, 'kept_amount' => 4000], 'return_exchanges_kept_only_when_free'],
    'committed without when' => [['status' => 'committed'], 'return_exchanges_committed'],
    'a second live exchange' => [[], 'return_exchanges_one_live_per_return'],
]);

it('lets a refund attempt give back an amount, and nothing else with it', function (array $changes, ?string $constraint): void {
    $attempt = fn () => $this->store->run(function () use ($changes): void {
        DB::table('return_refunds')->insert([
            'return_id' => ($this->insertReturn)(['resolution' => 'refund']), 'attempt' => 1, 'idempotency_key' => 'return:'.Str::ulid().':1', 'status' => 'started',
            'shipping' => false, 'withheld_amount' => 0, 'destination' => 'original_payment', 'requested_by_type' => 'staff_member', 'requested_by_id' => 'someone',
            'created_at' => now(), 'updated_at' => now(), ...$changes,
        ]);
    });

    $constraint === null ? $attempt() : expect($attempt)->toThrow(QueryException::class, $constraint);

    expect(true)->toBeTrue();
})->with([
    'an amount' => [['refund_amount' => 4000], null],
    'nothing' => [['refund_amount' => 0], 'return_refunds_amount'],
    'an amount and shipping' => [['refund_amount' => 4000, 'shipping' => true], 'return_refunds_amount_alone'],
    'an amount and something withheld' => [['refund_amount' => 4000, 'withheld_amount' => 100, 'withheld_reason' => 'Kept'], 'return_refunds_amount_alone'],
]);
