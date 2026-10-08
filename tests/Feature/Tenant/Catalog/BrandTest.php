<?php

declare(strict_types=1);

use App\Shared\Auth\AccessTokenIssuer;
use App\Shared\Auth\Models\Role;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Models\StoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;

/*
 * Sections 3.3, 11 and 13: brands have names in the store's languages, a
 * slug made once from the default-language name, and public IDs. Trashing a
 * brand keeps it; slugs are unique only outside the trash, and restoring
 * re-checks. catalog.view lets staff look, catalog.manage lets them change.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();

    $this->store = createStore('brand-store');
    $this->store->run(static fn (): bool => StoreSettings::query()->sole()->update(['enabled_locales' => ['en', 'fr']]));

    [$this->owner, $this->viewer, $this->outsider] = $this->store->run(static function (): array {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        $viewerRole = Role::findOrCreate('Catalog viewer', StaffMember::GUARD);
        $viewerRole->givePermissionTo(Permission::findOrCreate('catalog.view', StaffMember::GUARD));
        $viewer = StaffMember::factory()->create();
        $viewer->assignRole($viewerRole);

        return [$owner, $viewer, StaffMember::factory()->create()];
    });
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * @param  array<string, mixed>  $data
 */
function brandRequest(string $method, string $path = '', array $data = [], ?StaffMember $as = null, string $subdomain = 'brand-store'): TestResponse
{
    forgetSignIns();
    $store = $subdomain === 'brand-store' ? test()->store : test()->otherStore;
    $token = $store->run(static fn (): string => app(AccessTokenIssuer::class)->issue($as ?? test()->owner, StaffMember::GUARD, 'test')->plainTextToken);

    $response = test()->withToken($token)->json($method, storeUrl($subdomain, '/api/v1/staff/catalog/brands'.$path), $data);
    tenancy()->end();
    forgetSignIns();

    return $response;
}

it('adds a brand with its name in the store\'s languages, a slug from the default-language name and a public ID', function (): void {
    $response = brandRequest('POST', data: ['name' => ['en' => '  Ada Fabrics ', 'fr' => 'Tissus Ada'], 'description' => ['en' => 'Handwoven cloth.']])
        ->assertCreated()
        ->assertJsonPath('data.name', ['en' => 'Ada Fabrics', 'fr' => 'Tissus Ada'])
        ->assertJsonPath('data.description', ['en' => 'Handwoven cloth.'])
        ->assertJsonPath('data.slug', 'ada-fabrics')
        ->assertJsonPath('data.trashed_at', null);

    expect($response->json('data.id'))->toBeString()->toHaveLength(26)
        ->and($this->store->run(static fn (): int => Audit::query()->where('auditable_type', 'brand')->where('event', 'created')->count()))->toBe(1);
});

it('needs the default language, accepts only the store\'s languages, and checks the slug format', function (): void {
    brandRequest('POST', data: ['name' => ['fr' => 'Tissus Ada']])->assertUnprocessable()->assertJsonValidationErrors('name');
    brandRequest('POST', data: ['name' => ['en' => 'Ada', 'de' => 'Ada']])->assertUnprocessable()->assertJsonValidationErrors('name');
    brandRequest('POST', data: ['name' => 'Ada'])->assertUnprocessable()->assertJsonValidationErrors('name');
    brandRequest('POST', data: ['name' => ['en' => 'Ada'], 'slug' => 'Ada Fabrics'])->assertUnprocessable()->assertJsonValidationErrors('slug');

    expect($this->store->run(static fn (): int => Brand::query()->count()))->toBe(0);
});

it('gives a name without Latin letters a usable slug, and never reuses a slug outside the trash', function (): void {
    brandRequest('POST', data: ['name' => ['en' => 'ナイキ']])->assertCreated()->assertJsonPath('data.slug', 'brand');
    brandRequest('POST', data: ['name' => ['en' => 'ブランド']])->assertCreated()->assertJsonPath('data.slug', 'brand-1');

    brandRequest('POST', data: ['name' => ['en' => 'Other'], 'slug' => 'brand'])->assertUnprocessable()->assertJsonValidationErrors('slug');
});

it('changes only the languages sent, keeps the slug when renamed, and removes the description when it is null', function (): void {
    $id = brandRequest('POST', data: ['name' => ['en' => 'Ada Fabrics', 'fr' => 'Tissus Ada'], 'description' => ['en' => 'Cloth.']])->json('data.id');

    brandRequest('PATCH', "/{$id}", ['name' => ['en' => 'Ada Textiles']])
        ->assertOk()
        ->assertJsonPath('data.name', ['en' => 'Ada Textiles', 'fr' => 'Tissus Ada'])
        ->assertJsonPath('data.slug', 'ada-fabrics');

    brandRequest('PATCH', "/{$id}", ['name' => ['fr' => null], 'description' => null, 'slug' => 'ada-textiles'])
        ->assertOk()
        ->assertJsonPath('data.name', ['en' => 'Ada Textiles'])
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.slug', 'ada-textiles');

    brandRequest('PATCH', "/{$id}", ['name' => ['en' => null]])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('moves a brand to the trash, where it is kept, freeing its slug, and restores it only while no other brand uses the slug', function (): void {
    $id = brandRequest('POST', data: ['name' => ['en' => 'Ada Fabrics']])->json('data.id');

    brandRequest('DELETE', "/{$id}")->assertNoContent();

    brandRequest('GET')->assertOk()->assertJsonCount(0, 'data');
    brandRequest('GET', '?trashed=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    expect(brandRequest('GET', "/{$id}")->assertOk()->json('data.trashed_at'))->toBeString();
    brandRequest('PATCH', "/{$id}", ['name' => ['en' => 'Renamed']])->assertNotFound();

    $newId = brandRequest('POST', data: ['name' => ['en' => 'Ada Fabrics New'], 'slug' => 'ada-fabrics'])->assertCreated()->json('data.id');

    brandRequest('POST', "/{$id}/restore")->assertConflict()->assertJsonPath('code', 'brand_restore_conflict');

    brandRequest('PATCH', "/{$newId}", ['slug' => 'ada-fabrics-new'])->assertOk();
    brandRequest('POST', "/{$id}/restore")->assertOk()->assertJsonPath('data.trashed_at', null);
    brandRequest('POST', "/{$id}/restore")->assertOk();
    brandRequest('GET')->assertJsonCount(2, 'data');
});

it('lists brands a page at a time, sorted by slug', function (): void {
    foreach (['Zebra', 'Apple', 'Mango'] as $name) {
        brandRequest('POST', data: ['name' => ['en' => $name]])->assertCreated();
    }

    $firstPage = brandRequest('GET', '?per_page=2')->assertOk();
    expect(array_column($firstPage->json('data'), 'slug'))->toBe(['apple', 'mango']);

    $cursor = $firstPage->json('meta.next_cursor');
    expect(array_column(brandRequest('GET', '?per_page=2&cursor='.$cursor)->json('data'), 'slug'))->toBe(['zebra']);
});

it('lets catalog.view look but not change, and refuses staff without it', function (): void {
    $id = brandRequest('POST', data: ['name' => ['en' => 'Ada Fabrics']])->json('data.id');

    brandRequest('GET', as: $this->viewer)->assertOk()->assertJsonCount(1, 'data');
    brandRequest('GET', "/{$id}", as: $this->viewer)->assertOk();
    brandRequest('POST', data: ['name' => ['en' => 'Other']], as: $this->viewer)->assertForbidden();
    brandRequest('PATCH', "/{$id}", ['name' => ['en' => 'Other']], as: $this->viewer)->assertForbidden();
    brandRequest('DELETE', "/{$id}", as: $this->viewer)->assertForbidden();
    brandRequest('POST', "/{$id}/restore", as: $this->viewer)->assertForbidden();

    brandRequest('GET', as: $this->outsider)->assertForbidden();
    $this->withoutToken()->getJson(storeUrl('brand-store', '/api/v1/staff/catalog/brands'))->assertUnauthorized();
});

it('keeps slugs unique outside the trash and well-formed in the database itself', function (): void {
    $this->store->run(static function (): void {
        $brand = Brand::factory()->create(['slug' => 'ada']);
        $brand->delete();
        Brand::factory()->create(['slug' => 'ada']);

        expect(static fn () => Brand::factory()->create(['slug' => 'ada']))->toThrow(QueryException::class);
        expect(static fn () => DB::table('brands')->insert(['public_id' => (string) str()->ulid(), 'name' => '{"en":"X"}', 'slug' => 'Not A Slug']))->toThrow(QueryException::class);
        expect(static fn () => DB::table('brands')->insert(['public_id' => (string) str()->ulid(), 'name' => '{}', 'slug' => 'empty-name']))->toThrow(QueryException::class);
    });
});

it('never shows or changes another store\'s brands', function (): void {
    $this->otherStore = createStore('other-brand-store');
    $otherOwner = $this->otherStore->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });

    $id = brandRequest('POST', data: ['name' => ['en' => 'Ada Fabrics']])->json('data.id');

    brandRequest('GET', as: $otherOwner, subdomain: 'other-brand-store')->assertOk()->assertJsonCount(0, 'data');
    brandRequest('GET', "/{$id}", as: $otherOwner, subdomain: 'other-brand-store')->assertNotFound();
    brandRequest('DELETE', "/{$id}", as: $otherOwner, subdomain: 'other-brand-store')->assertNotFound();

    expect($this->store->run(static fn (): bool => Brand::query()->where('public_id', $id)->exists()))->toBeTrue();
});
