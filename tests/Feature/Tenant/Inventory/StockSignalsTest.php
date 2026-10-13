<?php

declare(strict_types=1);

use App\Tenant\Inventory\Actions\AdjustStock;
use App\Tenant\Inventory\Actions\ChangeInventorySettings;
use App\Tenant\Inventory\Actions\ReceiveStock;
use App\Tenant\Inventory\Actions\ReserveStock;
use App\Tenant\Inventory\Data\InventorySettingsData;
use App\Tenant\Inventory\Data\StockHolder;
use App\Tenant\Inventory\Data\StockLine;
use App\Tenant\Inventory\Enums\AdjustmentReason;
use App\Tenant\Inventory\Events\StockLevelChanged;
use App\Tenant\Inventory\Events\StockRunningLow;
use App\Tenant\Inventory\Events\VariantBackInStock;
use App\Tenant\Inventory\Events\VariantSoldOut;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 13: customers see whether a variant can be bought, never how many
 * are left. Inventory announces, after the change is saved, that a level
 * changed, and whether a counted variant sold out, came back or is running
 * low; other domains react. Nothing crosses stores.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('signal-store');
});

afterEach(function (): void {
    deleteAllStores();
});

it('tells customers whether each variant can be bought, never how many are left', function (): void {
    $this->store->run(static function (): void {
        $variants = variantsForSale(['IN', 'OUT', 'MADE', 'LATER', 'HELD']);
        $location = StockLocation::query()->sole();
        app(ReceiveStock::class)->handle($variants['IN'], $location, 3);
        app(ChangeInventorySettings::class)->handle($variants['MADE'], new InventorySettingsData(tracksStock: false));
        app(ChangeInventorySettings::class)->handle($variants['LATER'], new InventorySettingsData(allowsBackorder: true));
        app(ReceiveStock::class)->handle($variants['HELD'], $location, 1);
        app(ReserveStock::class)->handle(new StockHolder('checkout', 'someone'), [new StockLine($variants['HELD'], 1)]);
    });

    $response = $this->getJson(storeUrl('signal-store', '/api/v1/catalog/products/linen-shirt'))->assertOk();
    tenancy()->end();

    $availability = collect($response->json('data.variants'))->pluck('available_for_sale', 'sku')->all();
    expect($availability)->toBe(['IN' => true, 'OUT' => false, 'MADE' => true, 'LATER' => true, 'HELD' => false])
        ->and($response->getContent())->not->toContain('on_hand')->not->toContain('"available"')->not->toContain('reserved');

    $this->getJson(storeUrl('signal-store', '/api/v1/catalog/products'))->assertOk()->assertJsonPath('data.0.variants.0.available_for_sale', true);
    tenancy()->end();
});

it('announces when a counted variant sells out, comes back and runs low', function (): void {
    Event::fake([StockLevelChanged::class, VariantSoldOut::class, VariantBackInStock::class, StockRunningLow::class]);

    $this->store->run(static function (): void {
        $variant = variantsForSale(['IN'])['IN'];
        $location = StockLocation::query()->sole();

        app(ReceiveStock::class)->handle($variant, $location, 10);
        Event::assertDispatched(VariantBackInStock::class, static fn (VariantBackInStock $event): bool => $event->availableBefore === 0 && $event->availableAfter === 10);

        app(AdjustStock::class)->handle($variant, $location, -5, null, AdjustmentReason::Damaged);
        Event::assertDispatched(StockRunningLow::class, static fn (StockRunningLow $event): bool => $event->availableAfter === 5);

        // Already low: another drop isn't announced again.
        app(AdjustStock::class)->handle($variant, $location, -1, null, AdjustmentReason::Lost);

        app(ReserveStock::class)->handle(new StockHolder('checkout', 'last-buyer'), [new StockLine($variant, 4)]);
        Event::assertDispatched(VariantSoldOut::class, static fn (VariantSoldOut $event): bool => $event->variantId === $variant->id && $event->availableAfter === 0);

        Event::assertDispatchedTimes(StockLevelChanged::class, 4);
        Event::assertDispatchedTimes(StockRunningLow::class, 1);
    });
});

it('never announces a sell-out for variants that can still be ordered', function (): void {
    Event::fake([VariantSoldOut::class, StockRunningLow::class]);

    $this->store->run(static function (): void {
        $variants = variantsForSale(['LATER', 'MADE']);
        $location = StockLocation::query()->sole();
        app(ChangeInventorySettings::class)->handle($variants['LATER'], new InventorySettingsData(allowsBackorder: true));
        app(ChangeInventorySettings::class)->handle($variants['MADE'], new InventorySettingsData(tracksStock: false));

        app(ReceiveStock::class)->handle($variants['LATER'], $location, 1);
        app(ReserveStock::class)->handle(new StockHolder('checkout', 'buyer'), [new StockLine($variants['LATER'], 3)]);
        app(ReceiveStock::class)->handle($variants['MADE'], $location, 6);
        app(AdjustStock::class)->handle($variants['MADE'], $location, -6, null, AdjustmentReason::InternalUse);
    });

    Event::assertNotDispatched(VariantSoldOut::class);
    Event::assertNotDispatched(StockRunningLow::class);
});

it('announces nothing for a change that was rolled back', function (): void {
    $announced = [];
    Event::listen(StockLevelChanged::class, static function (StockLevelChanged $event) use (&$announced): void {
        $announced[] = $event->availableAfter;
    });

    $this->store->run(static function () use (&$announced): void {
        $variant = variantsForSale(['IN'])['IN'];

        DB::beginTransaction();
        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), 4);
        DB::rollBack();
        expect($announced)->toBe([]);

        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), 2);
    });

    expect($announced)->toBe([2]);
});

it('keeps reservations and availability inside their own store', function (): void {
    $other = createStore('other-signal-store');
    $buyer = new StockHolder('checkout', 'same-public-id');

    $this->store->run(static function () use ($buyer): void {
        $variant = variantsForSale(['IN'])['IN'];
        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), 1);
        app(ReserveStock::class)->handle($buyer, [new StockLine($variant, 1)]);
    });
    $other->run(static function () use ($buyer): void {
        $variant = variantsForSale(['IN'])['IN'];
        app(ReceiveStock::class)->handle($variant, StockLocation::query()->sole(), 2);

        expect(app(ReserveStock::class)->handle($buyer, [new StockLine($variant, 2)]))->toHaveCount(1)
            ->and(StockItem::query()->sole()->reserved)->toBe(2);
    });

    expect($this->store->run(static fn (): int => StockItem::query()->sole()->reserved))->toBe(1);

    $this->getJson(storeUrl('other-signal-store', '/api/v1/catalog/products/linen-shirt'))->assertOk()->assertJsonPath('data.variants.0.available_for_sale', false);
    tenancy()->end();
    $this->getJson(storeUrl('signal-store', '/api/v1/catalog/products/linen-shirt'))->assertOk()->assertJsonPath('data.variants.0.available_for_sale', false);
    tenancy()->end();
});
