<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\AdjustStock;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Enums\AdjustmentReason;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Services\StockLedger;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Support\SecondConnection;

/*
 * Section 13: stock is counted in whole units per variant per location,
 * every store has exactly one default location, every change is an
 * append-only stock movement with its cause, on hand never goes below zero,
 * and a scheduled reconciliation reports any level that doesn't match its
 * ledger. Inventory settings are audited; stock levels aren't (section 2.1).
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    $this->store = createStore('stock-store');

    [$this->owner, $this->viewer, $this->variant] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Stock viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Permission::findByName('inventory.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer, ProductVariant::factory()->create(['sku' => 'LIN-1'])];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  array<string, mixed>  $data
 */
function inventoryRequest(string $method, string $path, array $data = [], ?StaffMember $as = null, string $subdomain = 'stock-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'stock-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/inventory'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

it('gives every store exactly one default location, which stays active', function (): void {
    inventoryRequest('GET', '/locations', as: $this->viewer)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Main location')
        ->assertJsonPath('data.0.is_default', true);

    $this->store->run(static function (): void {
        $second = static fn () => DB::table('stock_locations')->insert(['public_id' => (string) Str::ulid(), 'name' => 'Second', 'is_default' => true, 'is_active' => true]);
        $inactiveDefault = static fn () => StockLocation::query()->where('is_default', true)->update(['is_active' => false]);

        expect($second)->toThrow(QueryException::class)
            ->and($inactiveDefault)->toThrow(QueryException::class);
    });
});

it('receives and corrects stock in whole units, recording each change with its cause', function (): void {
    $variant = $this->variant->public_id;

    inventoryRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 20, 'note' => 'Delivery 77'])
        ->assertCreated()
        ->assertJsonPath('data.levels.0.on_hand', 20)
        ->assertJsonPath('data.levels.0.available', 20);
    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['change' => -2, 'reason' => 'damaged'])->assertCreated()->assertJsonPath('data.levels.0.on_hand', 18);
    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['count' => 15, 'reason' => 'recount'])->assertCreated()->assertJsonPath('data.levels.0.on_hand', 15);
    // A count matching what is recorded changes nothing.
    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['count' => 15, 'reason' => 'recount'])->assertCreated();

    inventoryRequest('GET', "/variants/{$variant}/movements", as: $this->viewer)
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.type', 'adjusted')
        ->assertJsonPath('data.0.reason', 'recount')
        ->assertJsonPath('data.0.on_hand_change', -3)
        ->assertJsonPath('data.0.on_hand_after', 15)
        ->assertJsonPath('data.1.reason', 'damaged')
        ->assertJsonPath('data.2.type', 'received')
        ->assertJsonPath('data.2.note', 'Delivery 77')
        ->assertJsonPath('data.2.caused_by', ['type' => 'staff_member', 'id' => $this->owner->public_id]);

    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['change' => 1.5, 'reason' => 'found'])->assertUnprocessable()->assertJsonValidationErrors('change');
    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['change' => 1, 'count' => 3, 'reason' => 'found'])->assertUnprocessable()->assertJsonValidationErrors('change');
    inventoryRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 0])->assertUnprocessable()->assertJsonValidationErrors('quantity');
});

it('never lets on hand go below zero', function (): void {
    $variant = $this->variant->public_id;
    inventoryRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 3])->assertCreated();

    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['change' => -4, 'reason' => 'lost'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'stock_would_go_negative')
        ->assertJsonValidationErrors('change');

    // The database refuses it too.
    $this->store->run(static function (): void {
        expect(static fn () => StockItem::query()->update(['on_hand' => -1]))->toThrow(QueryException::class)
            ->and(StockItem::query()->sole()->on_hand)->toBe(3);
    });
});

it('never changes or deletes a stock movement once recorded', function (): void {
    inventoryRequest('POST', "/variants/{$this->variant->public_id}/receipts", ['quantity' => 5])->assertCreated();

    $this->store->run(static function (): void {
        $movement = StockMovement::query()->sole();

        expect(static fn () => DB::table('stock_movements')->update(['on_hand_delta' => 50]))->toThrow(QueryException::class, 'append-only')
            ->and(static fn () => DB::table('stock_movements')->delete())->toThrow(QueryException::class, 'append-only')
            ->and(static fn () => $movement->forceFill(['note' => 'changed'])->save())->toThrow(LogicException::class)
            ->and(StockMovement::query()->sole()->on_hand_delta)->toBe(5);
    });
});

it('lists levels for every variant, with low and out-of-stock filters using the store\'s threshold or the variant\'s own', function (): void {
    [$low, $plenty, $untracked] = $this->store->run(static fn (): array => [
        ProductVariant::factory()->create(['sku' => 'LOW-1']),
        ProductVariant::factory()->create(['sku' => 'MANY-1']),
        ProductVariant::factory()->create(['sku' => 'MADE-1']),
    ]);
    inventoryRequest('POST', "/variants/{$low->public_id}/receipts", ['quantity' => 4])->assertCreated();
    inventoryRequest('POST', "/variants/{$plenty->public_id}/receipts", ['quantity' => 40])->assertCreated();
    inventoryRequest('PATCH', "/variants/{$untracked->public_id}", ['tracks_stock' => false])->assertOk()->assertJsonPath('data.tracks_stock', false);

    inventoryRequest('GET', '/levels', as: $this->viewer)->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.0.low_stock_threshold', 5);
    inventoryRequest('GET', '/levels?status=out')->assertJsonPath('data.*.sku', ['LIN-1']);
    inventoryRequest('GET', '/levels?status=low')->assertJsonPath('data.*.sku', ['LOW-1']);

    // The variant's own threshold wins; the store's applies otherwise.
    inventoryRequest('PATCH', "/variants/{$plenty->public_id}", ['low_stock_threshold' => 50])->assertOk()->assertJsonPath('data.low_stock_threshold', 50);
    inventoryRequest('GET', '/levels?status=low')->assertJsonPath('data.*.sku', ['LOW-1', 'MANY-1']);
    $this->store->run(static fn () => StoreSettings::query()->sole()->update(['low_stock_threshold' => 3]));
    inventoryRequest('GET', '/levels?status=low')->assertJsonPath('data.*.sku', ['MANY-1']);
    inventoryRequest('PATCH', "/variants/{$plenty->public_id}", ['low_stock_threshold' => null])->assertOk()->assertJsonPath('data.low_stock_threshold', null);
    inventoryRequest('GET', '/levels?status=low')->assertJsonPath('data', []);
});

it('audits inventory settings but leaves stock levels to the movement ledger', function (): void {
    $variant = $this->variant->public_id;
    inventoryRequest('PATCH', "/variants/{$variant}", ['allows_backorder' => true, 'low_stock_threshold' => 2])->assertOk()->assertJsonPath('data.allows_backorder', true);
    inventoryRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 5])->assertCreated();

    $this->store->run(static function (): void {
        expect(Audit::query()->where('auditable_type', 'inventory_item')->count())->toBe(1)
            ->and(Audit::query()->where('auditable_type', 'stock_item')->count())->toBe(0)
            ->and(InventoryItem::query()->sole()->allows_backorder)->toBeTrue();
    });
});

it('lets only staff with the inventory permissions see or change stock', function (): void {
    $variant = $this->variant->public_id;
    $nobody = $this->store->run(static fn (): StaffMember => StaffMember::factory()->create());

    inventoryRequest('GET', '/levels', as: $nobody)->assertForbidden();
    inventoryRequest('GET', "/variants/{$variant}", as: $this->viewer)->assertOk();
    inventoryRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 1], as: $this->viewer)->assertForbidden();
    inventoryRequest('POST', "/variants/{$variant}/adjustments", ['change' => 1, 'reason' => 'found'], as: $this->viewer)->assertForbidden();
    inventoryRequest('PATCH', "/variants/{$variant}", ['tracks_stock' => false], as: $this->viewer)->assertForbidden();
});

it('waits for a concurrent change to the same stock level, so neither is lost', function (): void {
    $this->store->run(static function (): void {
        $variant = ProductVariant::query()->sole();
        $location = StockLocation::query()->sole();
        app(AdjustStock::class)->handle($variant, $location, 10, null, AdjustmentReason::Found);
        $adjust = static fn (): StockItem => app(AdjustStock::class)->handle($variant, $location, -3, null, AdjustmentReason::Damaged);

        // The other request holds the level and has changed it (10 to 15), but not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(StockItem::query()->sole());
        $otherRequest->run(static fn () => DB::connection(SecondConnection::NAME)->table('stock_items')->update(['on_hand' => 15]));

        expect($otherRequest->blocks($adjust))->toBeTrue();

        $otherRequest->commit();
        $adjust();

        // Worked from the other request's 15, not the 10 it could have read before.
        expect(StockItem::query()->sole()->on_hand)->toBe(12);
    });
});

it('reports any stock level that doesn\'t match its movement ledger', function (): void {
    inventoryRequest('POST', "/variants/{$this->variant->public_id}/receipts", ['quantity' => 5])->assertCreated();

    expect(Artisan::call('inventory:reconcile'))->toBe(0)
        ->and(Artisan::output())->toContain('matches its ledger');

    // Something changes a level outside the ledger.
    $this->store->run(static fn () => StockItem::query()->update(['on_hand' => 9]));

    expect(Artisan::call('inventory:reconcile'))->toBe(1)
        ->and(Artisan::output())->toContain($this->variant->public_id)->toContain('on hand 9 (ledger 5)');
});

it('keeps stock inside its own store', function (): void {
    $this->otherStore = createStore('other-stock-store');
    $otherOwner = $this->otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
    inventoryRequest('POST', "/variants/{$this->variant->public_id}/receipts", ['quantity' => 5])->assertCreated();

    inventoryRequest('GET', '/levels', as: $otherOwner, subdomain: 'other-stock-store')->assertOk()->assertJsonPath('data', []);
    inventoryRequest('GET', "/variants/{$this->variant->public_id}", as: $otherOwner, subdomain: 'other-stock-store')->assertNotFound();
});

it('holds a lock on every stock level it changes until the change is saved', function (): void {
    $this->store->run(static function (): void {
        $variant = ProductVariant::query()->sole();
        $location = StockLocation::query()->sole();
        app(ReceiveStock::class)->handle($variant, $location, 5);

        DB::beginTransaction();
        app(StockLedger::class)->lock([['location' => $location->id, 'variant' => $variant->id]]);

        // Another request trying to take the same level gives up after waiting.
        $otherRequest = SecondConnection::open();
        $takeTheLevel = static fn () => $otherRequest->run(static function (): void {
            DB::connection(SecondConnection::NAME)->statement("set local lock_timeout = '300ms'");
            DB::connection(SecondConnection::NAME)->table('stock_items')->lockForUpdate()->first();
        });

        expect($takeTheLevel)->toThrow(QueryException::class, 'lock timeout');

        $otherRequest->rollBack();
        DB::rollBack();
    });
});

it('reconciles every store\'s stock levels on a schedule', function (): void {
    $scheduled = array_filter(app(Illuminate\Console\Scheduling\Schedule::class)->events(), static fn ($event): bool => str_contains((string) $event->command, 'inventory:reconcile'));

    expect($scheduled)->toHaveCount(1);
});
