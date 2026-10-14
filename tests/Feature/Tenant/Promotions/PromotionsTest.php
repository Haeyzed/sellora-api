<?php

declare(strict_types=1);

use App\Shared\Auth\Models\Role;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\PromotionPricedRecords;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 11: staff set up promotions (percentage, fixed amount, free shipping,
 * buy X get Y), each type with only its own fields, amounts in the base
 * currency with exact amounts per offered currency, product, variant and
 * category lists, customer groups and dates read in the store's timezone.
 * promotions.view to see, promotions.manage to change; audited and logged.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('promotions-store', ['country_code' => 'NG']);

    [$this->owner, $this->viewer, $this->outsider] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $marketing = Role::findOrCreate('Marketing', StaffMember::GUARD);
        $marketing->givePermissionTo(Permission::findByName('promotions.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($marketing);

        return [$owner, $viewer, StaffMember::factory()->create()];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('creates a promotion with dates read in the store\'s timezone, and shows them there', function (): void {
    promotionRequest('POST', '', percentOffOrder(['percent' => '12.5', 'max_discount' => 500000, 'min_subtotal' => 1000000, 'starts_at' => '2030-11-27T00:00', 'ends_at' => '2030-12-01T00:00:00Z', 'combines_with' => ['shipping']]))
        ->assertCreated()
        ->assertJsonPath('data.type', 'percentage')
        ->assertJsonPath('data.discount_class', 'order')
        ->assertJsonPath('data.percent', '12.50')
        ->assertJsonPath('data.max_discount.amount', 500000)
        ->assertJsonPath('data.max_discount.currency', 'NGN')
        ->assertJsonPath('data.min_subtotal.amount', 1000000)
        ->assertJsonPath('data.starts_at', '2030-11-27T00:00:00+01:00')
        ->assertJsonPath('data.ends_at', '2030-12-01T01:00:00+01:00')
        ->assertJsonPath('data.combines_with', ['shipping'])
        ->assertJsonPath('data.includes_sale_items', false)
        ->assertJsonPath('data.status', 'scheduled');

    expect($this->store->run(static fn (): string => Promotion::query()->sole()->starts_at->toIso8601String()))->toBe('2030-11-26T23:00:00+00:00');
});

it('takes only the fields of the promotion\'s type', function (array $fields, string $error): void {
    promotionRequest('POST', '', [...percentOffOrder(['type' => null, 'applies_to' => null, 'percent' => null]), ...$fields])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'a percentage without its percent' => [['type' => 'percentage', 'applies_to' => 'order'], 'percent'],
    'a percentage with an amount off' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => '10', 'discount' => 100], 'discount'],
    'a percentage above 100' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => '100.01'], 'percent'],
    'a percentage of zero' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => '0'], 'percent'],
    'a percentage with three decimals' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => '12.345'], 'percent'],
    'a percentage as a number' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => 10], 'percent'],
    'a fixed amount without its amount' => [['type' => 'fixed_amount', 'applies_to' => 'order'], 'discount'],
    'a fixed amount with a cap' => [['type' => 'fixed_amount', 'applies_to' => 'order', 'discount' => 100, 'max_discount' => 50], 'max_discount'],
    'a fixed amount without what it applies to' => [['type' => 'fixed_amount', 'discount' => 100], 'applies_to'],
    'free shipping with what it applies to' => [['type' => 'free_shipping', 'applies_to' => 'order'], 'applies_to'],
    'free shipping with a percentage' => [['type' => 'free_shipping', 'percent' => '10'], 'percent'],
    'buy X get Y without how many to get' => [['type' => 'buy_x_get_y', 'percent' => '100', 'buy_quantity' => 2], 'get_quantity'],
    'repeats for anything but buy X get Y' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => '10', 'max_applications' => 2], 'max_applications'],
    'items to get for anything but buy X get Y' => [['type' => 'percentage', 'applies_to' => 'products', 'percent' => '10', 'products' => ['get' => []]], 'products.get'],
    'an unknown type' => [['type' => 'mystery'], 'type'],
    'an end before the start' => [['type' => 'free_shipping', 'starts_at' => '2030-01-02', 'ends_at' => '2030-01-01'], 'ends_at'],
]);

it('saves each type with its own fields, and keeps the type and method fixed afterwards', function (): void {
    $fixed = promotionRequest('POST', '', percentOffOrder(['type' => 'fixed_amount', 'percent' => null, 'discount' => 250000, 'method' => 'code']))
        ->assertCreated()->assertJsonPath('data.discount.amount', 250000)->assertJsonPath('data.method', 'code')->json('data.id');
    promotionRequest('POST', '', percentOffOrder(['type' => 'free_shipping', 'applies_to' => null, 'percent' => null, 'max_discount' => 300000]))
        ->assertCreated()->assertJsonPath('data.discount_class', 'shipping');
    promotionRequest('POST', '', percentOffOrder(['type' => 'buy_x_get_y', 'applies_to' => null, 'percent' => '100', 'buy_quantity' => 2, 'get_quantity' => 1, 'max_applications' => 3]))
        ->assertCreated()->assertJsonPath('data.discount_class', 'product')->assertJsonPath('data.buy_quantity', 2);

    promotionRequest('PATCH', "/{$fixed}", ['type' => 'percentage'])->assertJsonValidationErrors('type');
    promotionRequest('PATCH', "/{$fixed}", ['method' => 'automatic'])->assertJsonValidationErrors('method');
    promotionRequest('PATCH', "/{$fixed}", ['percent' => '10'])->assertJsonValidationErrors('percent');
    promotionRequest('PATCH', "/{$fixed}", ['discount' => 300000, 'name' => 'Three thousand off'])->assertOk()->assertJsonPath('data.discount.amount', 300000)->assertJsonPath('data.name', 'Three thousand off');
});

it('takes exact amounts only in the store\'s other currencies, replacing those set before', function (): void {
    $this->store->run(static fn () => offerStoreCurrency('USD', '0.00065'));
    $id = promotionRequest('POST', '', percentOffOrder(['type' => 'fixed_amount', 'percent' => null, 'discount' => 500000, 'currency_amounts' => [['currency' => 'USD', 'discount' => 300]]]))
        ->assertCreated()
        ->assertJsonPath('data.currency_amounts.0.currency', 'USD')
        ->assertJsonPath('data.currency_amounts.0.discount.amount', 300)
        ->json('data.id');

    promotionRequest('PATCH', "/{$id}", ['currency_amounts' => [['currency' => 'NGN', 'discount' => 1]]])->assertJsonValidationErrors('currency_amounts.0.currency');
    promotionRequest('PATCH', "/{$id}", ['currency_amounts' => [['currency' => 'EUR', 'discount' => 1]]])->assertJsonValidationErrors('currency_amounts.0.currency');
    promotionRequest('PATCH', "/{$id}", ['currency_amounts' => [['currency' => 'USD']]])->assertJsonValidationErrors('currency_amounts.0');
    promotionRequest('PATCH', "/{$id}", ['currency_amounts' => [['currency' => 'USD', 'max_discount' => 5]]])->assertJsonValidationErrors('currency_amounts.0.max_discount');
    promotionRequest('PATCH', "/{$id}", ['currency_amounts' => []])->assertOk()->assertJsonPath('data.currency_amounts', []);
});

it('limits a promotion to products, variants and categories, and to customer groups, replacing each list sent', function (): void {
    [$shirt, $hat, $variant, $sale, $group] = $this->store->run(static function (): array {
        $shirt = Product::factory()->create();

        return [$shirt->public_id, Product::factory()->create()->public_id, ProductVariant::factory()->create(['product_id' => $shirt->id])->public_id, Category::factory()->create()->public_id, CustomerGroup::factory()->create()->public_id];
    });

    $id = promotionRequest('POST', '', percentOffOrder(['applies_to' => 'products', 'products' => ['include' => [$shirt, $hat]], 'variants' => ['exclude' => [$variant]], 'categories' => ['include' => [$sale]], 'customer_groups' => [$group]]))
        ->assertCreated()
        ->assertJsonPath('data.products.include', collect([$shirt, $hat])->sort()->values()->all())
        ->assertJsonPath('data.variants', ['include' => [], 'exclude' => [$variant], 'get' => []])
        ->assertJsonPath('data.categories.include', [$sale])
        ->assertJsonPath('data.customer_groups', [$group])
        ->json('data.id');

    // Lists not sent stay as they are.
    promotionRequest('PATCH', "/{$id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.customer_groups', [$group])->assertJsonPath('data.categories.include', [$sale]);

    // Lists sent replace those lists.
    promotionRequest('PATCH', "/{$id}", ['products' => ['include' => [$hat]], 'customer_groups' => []])
        ->assertOk()
        ->assertJsonPath('data.products.include', [$hat])
        ->assertJsonPath('data.variants.exclude', [$variant])
        ->assertJsonPath('data.customer_groups', []);

    $trashed = $this->store->run(static fn (): string => tap(Product::factory()->create(), static fn (Product $product) => $product->delete())->public_id);
    promotionRequest('PATCH', "/{$id}", ['products' => ['include' => [$trashed]]])->assertJsonValidationErrors('products.include.0');
    promotionRequest('PATCH', "/{$id}", ['customer_groups' => ['01JUNKJUNKJUNKJUNKJUNKJUNK']])->assertJsonValidationErrors('customer_groups.0');

    // Deleting what a promotion points at takes it off the list.
    $this->store->run(static fn () => CustomerGroup::query()->delete());
    promotionRequest('PATCH', "/{$id}", ['customer_groups' => [$group]])->assertJsonValidationErrors('customer_groups.0');
});

it('lists promotions newest first, narrowed by status, method, type or name', function (): void {
    $this->store->run(static function (): void {
        Promotion::factory()->create(['name' => 'Running sale']);
        Promotion::factory()->create(['name' => 'Next week', 'starts_at' => now()->addWeek()]);
        Promotion::factory()->create(['name' => 'Last month', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);
        Promotion::factory()->withCode()->create(['name' => 'Switched off', 'is_active' => false]);
        Promotion::factory()->create(['name' => 'Old'])->delete();
    });

    $names = static fn (string $query = ''): array => array_column(promotionRequest('GET', $query, as: test()->viewer)->assertOk()->json('data'), 'name');

    expect($names())->toBe(['Switched off', 'Last month', 'Next week', 'Running sale'])
        ->and($names('?status=running'))->toBe(['Running sale'])
        ->and($names('?status=scheduled'))->toBe(['Next week'])
        ->and($names('?status=ended'))->toBe(['Last month'])
        ->and($names('?status=inactive'))->toBe(['Switched off'])
        ->and($names('?status=archived'))->toBe(['Old'])
        ->and($names('?method=code'))->toBe(['Switched off'])
        ->and($names('?search=SALE'))->toBe(['Running sale']);

    promotionRequest('GET', '', as: $this->viewer)->assertJsonPath('meta.total', 4);
});

it('activates, deactivates and archives a promotion, audited and logged by public ID', function (): void {
    $id = promotionRequest('POST', '', percentOffOrder())->assertCreated()->assertJsonPath('data.status', 'running')->json('data.id');

    promotionRequest('POST', "/{$id}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
    promotionRequest('POST', "/{$id}/activate")->assertOk()->assertJsonPath('data.status', 'running');
    promotionRequest('PATCH', "/{$id}", ['name' => 'Renamed', 'usage_limit' => 100])->assertOk();
    promotionRequest('DELETE', "/{$id}")->assertNoContent();

    promotionRequest('GET', "/{$id}", as: $this->viewer)->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.archived_at', fn (?string $at): bool => $at !== null);
    promotionRequest('PATCH', "/{$id}", ['name' => 'Too late'])->assertNotFound();
    promotionRequest('POST', "/{$id}/activate")->assertNotFound();

    $entries = $this->store->run(static fn (): array => Activity::query()->inLog('promotions')->orderBy('id')->get(['event', 'description', 'properties'])->toArray());
    expect(array_column($entries, 'event'))->toBe(['promotion_created', 'promotion_deactivated', 'promotion_activated', 'promotion_updated', 'promotion_archived'])
        ->and($entries[3]['properties'])->toBe(['fields' => ['name', 'usage_limit']])
        ->and($entries[4]['description'])->toBe("Archived promotion {$id}");

    expect($this->store->run(static fn (): array => Audit::query()->where('auditable_type', 'promotion')->orderBy('id')->pluck('event')->all()))
        ->toBe(['created', 'updated', 'updated', 'updated', 'deleted']);
});

it('needs promotions.view to see promotions and promotions.manage to change them', function (string $method, string $path, StaffMemberKind $as): void {
    $id = $this->store->run(static fn (): string => Promotion::factory()->create()->public_id);

    promotionRequest($method, str_replace('{promotion}', $id, $path), percentOffOrder(), $as === StaffMemberKind::Viewer ? $this->viewer : $this->outsider)->assertForbidden();
})->with([
    'list, without promotions.view' => ['GET', '', StaffMemberKind::Outsider],
    'show, without promotions.view' => ['GET', '/{promotion}', StaffMemberKind::Outsider],
    'create, with view only' => ['POST', '', StaffMemberKind::Viewer],
    'change, with view only' => ['PATCH', '/{promotion}', StaffMemberKind::Viewer],
    'archive, with view only' => ['DELETE', '/{promotion}', StaffMemberKind::Viewer],
    'activate, with view only' => ['POST', '/{promotion}/activate', StaffMemberKind::Viewer],
    'deactivate, with view only' => ['POST', '/{promotion}/deactivate', StaffMemberKind::Viewer],
]);

it('locks the base currency once a promotion holds an amount in it', function (): void {
    $priced = fn (): bool => $this->store->run(static fn (): bool => app(PromotionPricedRecords::class)->exist());

    promotionRequest('POST', '', percentOffOrder())->assertCreated();
    expect($priced())->toBeFalse();

    $id = promotionRequest('POST', '', percentOffOrder(['min_subtotal' => 100]))->assertCreated()->json('data.id');
    expect($priced())->toBeTrue();

    // Without its last amount it holds nothing in the base currency any more.
    promotionRequest('PATCH', "/{$id}", ['min_subtotal' => null])->assertOk()->assertJsonPath('data.min_subtotal', null);
    expect($priced())->toBeFalse();
});

it('keeps each type\'s fields to that type in the database too', function (array $row): void {
    expect(fn () => $this->store->run(static fn () => DB::table('promotions')->insert([
        'public_id' => strtolower((string) Str::ulid()), 'name' => 'x', 'title' => '{"en":"x"}', 'method' => 'automatic', 'starts_at' => now(), 'created_at' => now(), 'updated_at' => now(), ...$row,
    ])))->toThrow(QueryException::class, 'promotions_');
})->with([
    'a percentage without its percent' => [['type' => 'percentage', 'applies_to' => 'order']],
    'a fixed amount with a percent' => [['type' => 'fixed_amount', 'applies_to' => 'order', 'percent' => 10, 'discount_amount' => 100, 'currency' => 'NGN']],
    'free shipping applied to products' => [['type' => 'free_shipping', 'applies_to' => 'products']],
    'an amount without its currency' => [['type' => 'free_shipping', 'max_discount_amount' => 100]],
    'a percent above 100' => [['type' => 'percentage', 'applies_to' => 'order', 'percent' => 100.5]],
    'an end before the start' => [['type' => 'free_shipping', 'ends_at' => now()->subDay()]],
]);

it('never shows one store\'s promotions to another store\'s staff', function (): void {
    $id = promotionRequest('POST', '', percentOffOrder())->json('data.id');
    $otherStore = createStore('second-store');
    $theirOwner = $otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    forgetSignIns();
    $this->withToken(staffTokenFor($theirOwner, 'second-store'))->getJson(storeUrl('second-store', '/api/v1/staff/promotions'))->assertOk()->assertJsonCount(0, 'data');
    tenancy()->end();
    forgetSignIns();
    $this->withToken(staffTokenFor($theirOwner, 'second-store'))->getJson(storeUrl('second-store', "/api/v1/staff/promotions/{$id}"))->assertNotFound();
});
