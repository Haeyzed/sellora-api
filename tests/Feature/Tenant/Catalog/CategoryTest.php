<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Services\CategoryTree;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;

/*
 * Sections 3.3, 11 and 13: categories form a tree of limited depth, ordered
 * among siblings, with names in the store's languages and public IDs. A
 * category can't move under itself, and can only go to the trash once it
 * has no subcategories outside the trash. Slugs are unique outside the
 * trash; restoring re-checks the slug, the parent and the depth.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    config(['catalog.category_max_depth' => 3]);

    $this->store = createStore('category-store');

    [$this->owner, $this->viewer] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Catalog viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Permission::findOrCreate('catalog.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  array<string, mixed>  $data
 */
function categoryRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'category-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'category-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/categories'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

/**
 * Adds a category through the API and returns its public ID.
 */
function addCategory(string $name, ?string $parent = null): string
{
    return categoryRequest('POST', data: ['name' => ['en' => $name], 'parent' => $parent])->assertCreated()->json('data.id');
}

it('adds categories at the top level or under another, each last among its siblings, with a slug and a public ID', function (): void {
    $clothing = addCategory('Clothing');
    $shoes = addCategory('Shoes');

    categoryRequest('POST', data: ['name' => ['en' => 'Men\'s shirts'], 'description' => ['en' => 'Formal and casual.'], 'parent' => $clothing])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $clothing)
        ->assertJsonPath('data.slug', 'mens-shirts')
        ->assertJsonPath('data.position', 0)
        ->assertJsonPath('data.subcategories_count', 0);

    categoryRequest('GET', "/{$shoes}")->assertOk()->assertJsonPath('data.position', 1)->assertJsonPath('data.parent_id', null);
    categoryRequest('GET', "/{$clothing}")->assertOk()->assertJsonPath('data.subcategories_count', 1);

    expect($this->store->run(static fn (): int => Audit::query()->where('auditable_type', 'category')->where('event', 'created')->count()))->toBe(3);
});

it('refuses to nest categories deeper than the store allows, whether adding or moving a branch', function (): void {
    $level1 = addCategory('Level 1');
    $level2 = addCategory('Level 2', $level1);
    $level3 = addCategory('Level 3', $level2);

    categoryRequest('POST', data: ['name' => ['en' => 'Level 4'], 'parent' => $level3])
        ->assertUnprocessable()->assertJsonPath('code', 'category_too_deep')->assertJsonValidationErrors('parent');

    $branch = addCategory('Branch');
    addCategory('Leaf', $branch);

    categoryRequest('PATCH', "/{$branch}", ['parent' => $level2])->assertUnprocessable()->assertJsonPath('code', 'category_too_deep');
    categoryRequest('PATCH', "/{$branch}", ['parent' => $level1])->assertOk()->assertJsonPath('data.parent_id', $level1);
});

it('never moves a category under itself or one of its own subcategories', function (): void {
    $clothing = addCategory('Clothing');
    $men = addCategory('Men', $clothing);

    categoryRequest('PATCH', "/{$clothing}", ['parent' => $men])->assertUnprocessable()->assertJsonPath('code', 'category_loop');
    categoryRequest('PATCH', "/{$clothing}", ['parent' => $clothing])->assertUnprocessable()->assertJsonPath('code', 'category_loop');
});

it('moves a category to the top level, last among its new siblings, and keeps its slug when renamed', function (): void {
    $clothing = addCategory('Clothing');
    $men = addCategory('Men', $clothing);

    categoryRequest('PATCH', "/{$men}", ['parent' => null, 'name' => ['en' => 'Menswear']])
        ->assertOk()
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.position', 1)
        ->assertJsonPath('data.name', ['en' => 'Menswear'])
        ->assertJsonPath('data.slug', 'men');
});

it('trashes only a category without subcategories outside the trash, and restores it only into a live parent', function (): void {
    $clothing = addCategory('Clothing');
    $men = addCategory('Men', $clothing);

    categoryRequest('DELETE', "/{$clothing}")->assertConflict()->assertJsonPath('code', 'category_has_subcategories');

    categoryRequest('DELETE', "/{$men}")->assertNoContent();
    categoryRequest('DELETE', "/{$clothing}")->assertNoContent();
    categoryRequest('GET', '?trashed=1')->assertOk()->assertJsonCount(2, 'data');
    categoryRequest('PATCH', "/{$men}", ['name' => ['en' => 'Renamed']])->assertNotFound();

    categoryRequest('POST', "/{$men}/restore")->assertConflict()->assertJsonPath('code', 'category_parent_in_trash');

    categoryRequest('POST', "/{$clothing}/restore")->assertOk()->assertJsonPath('data.trashed_at', null);
    categoryRequest('POST', "/{$men}/restore")->assertOk()->assertJsonPath('data.parent_id', $clothing);
});

it('frees a trashed category\'s slug, and restores it only while no other category uses it', function (): void {
    $sale = addCategory('Sale');
    categoryRequest('DELETE', "/{$sale}")->assertNoContent();

    $newSale = categoryRequest('POST', data: ['name' => ['en' => 'Sale']])->assertCreated()->assertJsonPath('data.slug', 'sale-1')->json('data.id');
    categoryRequest('PATCH', "/{$newSale}", ['slug' => 'sale'])->assertOk();

    categoryRequest('POST', "/{$sale}/restore")->assertConflict()->assertJsonPath('code', 'category_restore_conflict');
});

it('checks the depth again when restoring, since the parent may have moved deeper', function (): void {
    $top = addCategory('Top');
    $middle = addCategory('Middle', $top);
    $parent = addCategory('Parent');
    $child = addCategory('Child', $parent);

    categoryRequest('DELETE', "/{$child}")->assertNoContent();
    categoryRequest('PATCH', "/{$parent}", ['parent' => $middle])->assertOk();

    categoryRequest('POST', "/{$child}/restore")->assertUnprocessable()->assertJsonPath('code', 'category_too_deep');
});

it('reorders a parent\'s subcategories only when every one is listed exactly once', function (): void {
    $clothing = addCategory('Clothing');
    $men = addCategory('Men', $clothing);
    $women = addCategory('Women', $clothing);
    $kids = addCategory('Kids', $clothing);
    $other = addCategory('Other');

    categoryRequest('PUT', '/order', ['parent' => $clothing, 'categories' => [$kids, $men]])
        ->assertUnprocessable()->assertJsonPath('code', 'category_order_mismatch');
    categoryRequest('PUT', '/order', ['parent' => $clothing, 'categories' => [$kids, $men, $other]])
        ->assertUnprocessable()->assertJsonPath('code', 'category_order_mismatch');

    categoryRequest('PUT', '/order', ['parent' => $clothing, 'categories' => [$kids, $women, $men]])->assertNoContent();
    expect(array_column(categoryRequest('GET', "?parent={$clothing}")->json('data'), 'id'))->toBe([$kids, $women, $men]);

    categoryRequest('PUT', '/order', ['parent' => null, 'categories' => [$other, $clothing]])->assertNoContent();
    expect(array_column(categoryRequest('GET', '?parent=root')->json('data'), 'id'))->toBe([$other, $clothing]);
});

it('lists one level of the tree, every category, or the trash, a page at a time', function (): void {
    $clothing = addCategory('Clothing');
    addCategory('Men', $clothing);
    addCategory('Shoes');

    expect(categoryRequest('GET', '?parent=root')->json('data'))->toHaveCount(2)
        ->and(categoryRequest('GET')->json('data'))->toHaveCount(3)
        ->and(categoryRequest('GET', '?per_page=1')->json('meta.next_cursor'))->toBeString();

    categoryRequest('GET', '?parent=01J00000000000000000000000')->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('lets catalog.view look but not change', function (): void {
    $clothing = addCategory('Clothing');

    categoryRequest('GET', as: $this->viewer)->assertOk();
    categoryRequest('GET', "/{$clothing}", as: $this->viewer)->assertOk();
    categoryRequest('POST', data: ['name' => ['en' => 'Other']], as: $this->viewer)->assertForbidden();
    categoryRequest('PATCH', "/{$clothing}", ['name' => ['en' => 'Other']], as: $this->viewer)->assertForbidden();
    categoryRequest('PUT', '/order', ['parent' => null, 'categories' => [$clothing]], as: $this->viewer)->assertForbidden();
    categoryRequest('DELETE', "/{$clothing}", as: $this->viewer)->assertForbidden();
    categoryRequest('POST', "/{$clothing}/restore", as: $this->viewer)->assertForbidden();
});

it('keeps slugs unique outside the trash, and a category from being its own parent, in the database itself', function (): void {
    $this->store->run(static function (): void {
        $category = Category::factory()->create(['slug' => 'sale']);
        $category->delete();
        Category::factory()->create(['slug' => 'sale']);

        expect(static fn () => Category::factory()->create(['slug' => 'sale']))->toThrow(QueryException::class);

        $loner = Category::factory()->create();
        expect(static fn () => DB::table('categories')->where('id', $loner->id)->update(['parent_id' => $loner->id]))->toThrow(QueryException::class);
    });
});

it('locks the tree only inside a transaction, where the lock lasts until it ends', function (): void {
    $this->store->run(static function (): void {
        expect(static fn () => app(CategoryTree::class)->lockForChanges())->toThrow(LogicException::class);
    });
});

it('never shows or changes another store\'s categories', function (): void {
    $this->otherStore = createStore('other-category-store');
    $otherOwner = $this->otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $clothing = addCategory('Clothing');

    categoryRequest('GET', as: $otherOwner, subdomain: 'other-category-store')->assertOk()->assertJsonCount(0, 'data');
    categoryRequest('GET', "/{$clothing}", as: $otherOwner, subdomain: 'other-category-store')->assertNotFound();
    categoryRequest('POST', data: ['name' => ['en' => 'Men'], 'parent' => $clothing], as: $otherOwner, subdomain: 'other-category-store')
        ->assertUnprocessable()->assertJsonValidationErrors('parent');
});
