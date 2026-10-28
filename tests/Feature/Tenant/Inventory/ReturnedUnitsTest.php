<?php

declare(strict_types=1);

use App\Shared\Auth\AccountReference;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Actions\RestockReturnedUnits;
use App\Tenant\Inventory\Actions\WriteOffReturnedUnits;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Data\StockSource;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SecondConnection;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18.3: units a buyer sent back, recorded by Inventory for Returns.
 * Resellable units go back on the shelf ("restocked"); damaged ones are
 * recorded as they arrive and written off at once, so the ledger shows every
 * unit that came back. Nothing is held for anyone, a variant whose stock
 * isn't counted is left out, and the levels are changed under their locks.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->store = createStore('stock-store');

    [$this->staff, $this->shirt, $this->socks, $this->madeToOrder] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);
        $location = StockLocation::query()->sole();
        [$shirt, $socks, $madeToOrder] = [ProductVariant::factory()->create(), ProductVariant::factory()->create(), ProductVariant::factory()->create()];
        app(ReceiveStock::class)->handle($shirt, $location, 4);
        app(ReceiveStock::class)->handle($socks, $location, 2);
        app(ChangeInventorySettings::class)->handle($madeToOrder, new InventorySettingsData(tracksStock: false));

        return [$owner, $shirt, $socks, $madeToOrder];
    });
    $this->return = new StockSource('return', (string) Str::ulid());
});

afterEach(function (): void {
    deleteAllStores();
});

it('puts returned units back on the shelf, held for no one, recording the return and who did it', function (): void {
    $this->store->run(function (): void {
        $location = StockLocation::query()->sole();
        $by = AccountReference::to($this->staff);

        $restocked = app(RestockReturnedUnits::class)->handle($this->return, $location, [$this->shirt->id => 2, $this->socks->id => 1, $this->madeToOrder->id => 1], $by);

        $levels = StockItem::query()->orderBy('product_variant_id')->get()->mapWithKeys(static fn (StockItem $item): array => [$item->product_variant_id => [$item->on_hand, $item->reserved]])->all();
        $movements = StockMovement::query()->whereIn('type', ['restocked'])->orderBy('product_variant_id')->get();

        // The made-to-order variant never left the shelf: nothing of it is recorded.
        expect($restocked)->toBe(3)
            ->and($levels)->toBe([$this->shirt->id => [6, 0], $this->socks->id => [3, 0]])
            ->and($movements->map(static fn (StockMovement $movement): array => [$movement->product_variant_id, $movement->on_hand_delta, $movement->reserved_delta, $movement->source_type, $movement->source_id, $movement->causer_id])->all())->toBe([
                [$this->shirt->id, 2, 0, 'return', $this->return->id, $this->staff->public_id],
                [$this->socks->id, 1, 0, 'return', $this->return->id, $this->staff->public_id],
            ])
            ->and(app(RestockReturnedUnits::class)->handle($this->return, $location, [$this->madeToOrder->id => 2, $this->shirt->id => 0]))->toBe(0);
    });

    expect(Artisan::call('inventory:reconcile'))->toBe(0);
});

it('writes off damaged returned units, recording them as they arrive and then written off', function (): void {
    $this->store->run(function (): void {
        $location = StockLocation::query()->sole();

        $writtenOff = app(WriteOffReturnedUnits::class)->handle($this->return, $location, [$this->shirt->id => 3, $this->madeToOrder->id => 1]);

        $movements = StockMovement::query()->where('product_variant_id', $this->shirt->id)->whereIn('type', ['returned_damaged', 'written_off'])->orderBy('id')->get();

        expect($writtenOff)->toBe(3)
            ->and(StockItem::query()->where('product_variant_id', $this->shirt->id)->sole()->on_hand)->toBe(4)
            ->and($movements->map(static fn (StockMovement $movement): array => [$movement->type->value, $movement->on_hand_delta, $movement->on_hand_after, $movement->source_id])->all())->toBe([
                ['returned_damaged', 3, 7, $this->return->id],
                ['written_off', -3, 4, $this->return->id],
            ])
            ->and(StockMovement::query()->where('product_variant_id', $this->madeToOrder->id)->exists())->toBeFalse();
    });

    expect(Artisan::call('inventory:reconcile'))->toBe(0);
});

it('waits on its own lock of the stock level while another request holds it', function (): void {
    $this->store->run(function (): void {
        $location = StockLocation::query()->sole();
        $restock = fn (): int => app(RestockReturnedUnits::class)->handle($this->return, $location, [$this->shirt->id => 2]);

        // The other request holds the shirt's level, as a change to it would, without having written anything yet.
        $otherRequest = SecondConnection::open()->holdRowLock(StockItem::query()->where('product_variant_id', $this->shirt->id)->sole());

        expect($otherRequest->blockedStatement($restock))->toContain('"stock_items"')->toContain('for update');

        $otherRequest->commit();
        $restock();

        expect(StockItem::query()->where('product_variant_id', $this->shirt->id)->sole()->on_hand)->toBe(6);
    });
});

it('works from a change another request committed while it waited, so neither is lost', function (): void {
    $this->store->run(function (): void {
        $location = StockLocation::query()->sole();
        $restock = fn (): int => app(RestockReturnedUnits::class)->handle($this->return, $location, [$this->shirt->id => 2]);

        // The other request holds the shirt's level and has changed it (4 to 9), but not yet committed.
        $otherRequest = SecondConnection::open()->holdRowLock(StockItem::query()->where('product_variant_id', $this->shirt->id)->sole());
        $otherRequest->run(fn () => DB::connection(SecondConnection::NAME)->table('stock_items')->where('product_variant_id', $this->shirt->id)->update(['on_hand' => 9]));

        expect($otherRequest->blocks($restock))->toBeTrue();

        $otherRequest->commit();
        $restock();

        // Worked from the other request's 9, not the 4 it could have read before.
        expect(StockItem::query()->where('product_variant_id', $this->shirt->id)->sole()->on_hand)->toBe(11);
    });
});
