<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Shared\Money\Money;
use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Catalog\Enums\ProductStatus;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Sections 3.3 and 13: a unit cost is an internal decimal with six decimal
 * places in the store's base currency, moved here from the catalog. Only
 * staff with the inventory.costs permission see or change it; customers
 * never do. Costs lock the base currency and are audited.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('cost-store');

    [$this->owner, $this->stockKeeper, $this->variant] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $role = Role::findOrCreate('Stock keeper', StaffMember::GUARD);
        $role->givePermissionTo(Permission::findByName('inventory.view', StaffMember::GUARD), Permission::findByName('inventory.adjust', StaffMember::GUARD));
        $stockKeeper = StaffMember::factory()->create();
        $stockKeeper->assignRole($role);

        $product = Product::factory()->create(['name' => ['en' => 'Linen shirt'], 'slug' => 'linen-shirt']);
        $product->forceFill(['status' => ProductStatus::Active])->save();

        return [$owner, $stockKeeper, ProductVariant::factory()->create(['product_id' => $product->id, 'price' => Money::ofMinor(1999, 'NGN'), 'currency' => 'NGN'])];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('lets only staff with the costs permission see or change a unit cost', function (): void {
    $variant = $this->variant->public_id;

    costRequest('PATCH', "/variants/{$variant}", ['unit_cost' => '4.250000'])
        ->assertOk()
        ->assertJsonPath('data.unit_cost', ['amount' => '4.250000', 'currency' => 'NGN']);

    costRequest('PATCH', "/variants/{$variant}", ['unit_cost' => '5'], as: $this->stockKeeper)->assertForbidden();
    costRequest('PATCH', "/variants/{$variant}", ['allows_backorder' => true], as: $this->stockKeeper)->assertOk()->assertJsonMissingPath('data.unit_cost');
    costRequest('GET', "/variants/{$variant}", as: $this->stockKeeper)->assertOk()->assertJsonMissingPath('data.unit_cost');

    costRequest('PATCH', "/variants/{$variant}", ['unit_cost' => null])->assertOk()->assertJsonPath('data.unit_cost', null);
});

it('takes a unit cost as a decimal string with up to six decimal places, never a number', function (): void {
    $variant = $this->variant->public_id;

    costRequest('PATCH', "/variants/{$variant}", ['unit_cost' => '0.000125'])->assertOk()->assertJsonPath('data.unit_cost.amount', '0.000125');

    foreach ([4.25, '0.0000001', '-1', 'abc', '1e3'] as $invalid) {
        costRequest('PATCH', "/variants/{$variant}", ['unit_cost' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('unit_cost');
    }

    expect($this->store->run(static fn (): string => (string) DB::table('inventory_items')->value('unit_cost')))->toBe('0.000125');
});

it('records the cost a receipt arrived at, which becomes the variant\'s unit cost', function (): void {
    $variant = $this->variant->public_id;

    costRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 10, 'unit_cost' => '3.5'])->assertCreated()->assertJsonPath('data.unit_cost.amount', '3.500000');
    costRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 1, 'unit_cost' => '3.5'], as: $this->stockKeeper)->assertForbidden();
    costRequest('POST', "/variants/{$variant}/receipts", ['quantity' => 2], as: $this->stockKeeper)->assertCreated();

    costRequest('GET', "/variants/{$variant}/movements")->assertJsonPath('data.1.unit_cost', '3.500000')->assertJsonPath('data.0.unit_cost', null);
    costRequest('GET', "/variants/{$variant}/movements", as: $this->stockKeeper)->assertJsonMissingPath('data.1.unit_cost');

    // Only receipts carry a cost.
    $this->store->run(static function (): void {
        expect(static fn () => DB::table('stock_movements')->insert([
            'public_id' => (string) Str::ulid(), 'stock_item_id' => DB::table('stock_items')->value('id'), 'product_variant_id' => ProductVariant::query()->value('id'),
            'stock_location_id' => DB::table('stock_locations')->value('id'), 'type' => 'adjusted', 'on_hand_delta' => 1, 'reserved_delta' => 0,
            'on_hand_after' => 13, 'reserved_after' => 0, 'unit_cost' => '1.000000',
        ]))->toThrow(QueryException::class);
    });
});

it('locks the base currency once a cost exists, and audits every cost change', function (): void {
    $this->store->run(static fn () => DB::table('product_variants')->update(['price_amount' => null, 'currency' => null]));
    expect($this->store->run(static fn (): bool => app(PricedRecordsRegistry::class)->anyExist()))->toBeFalse();

    costRequest('PATCH', "/variants/{$this->variant->public_id}", ['unit_cost' => '2'])->assertOk();
    costRequest('PATCH', "/variants/{$this->variant->public_id}", ['unit_cost' => '2.5'])->assertOk();

    $this->store->run(static function (): void {
        expect(app(PricedRecordsRegistry::class)->anyExist())->toBeTrue();

        $last = Audit::query()->where('auditable_type', 'inventory_item')->latest('id')->first();
        expect($last?->old_values)->toBe(['unit_cost' => '2.000000'])
            ->and($last?->new_values)->toBe(['unit_cost' => '2.500000'])
            ->and(InventoryItem::query()->sole()->unit_cost?->toString())->toBe('2.500000');
    });
});

it('never shows a cost to customers', function (): void {
    costRequest('PATCH', "/variants/{$this->variant->public_id}", ['unit_cost' => '7.777777'])->assertOk();
    costRequest('POST', "/variants/{$this->variant->public_id}/receipts", ['quantity' => 5, 'unit_cost' => '7.777777'])->assertCreated();
    forgetSignIns();

    $response = $this->getJson(storeUrl('cost-store', '/api/v1/catalog/products/linen-shirt'))->assertOk();
    tenancy()->end();

    expect($response->getContent())->not->toContain('cost')->not->toContain('7.777777');
    expect($this->store->run(static fn (): int => StockMovement::query()->whereNotNull('unit_cost')->count()))->toBe(1);
});
