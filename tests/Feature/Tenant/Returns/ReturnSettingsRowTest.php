<?php

declare(strict_types=1);

use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Returns\Actions\ChangeReturnSettings;
use App\Tenant\Returns\Exceptions\MissingReturnSettingsException;
use App\Tenant\Returns\Models\ReturnSettings;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 18d: a store's return settings are a row it always has (CLAUDE.md
 * section 10). Stores set up before get it from a migration, with the
 * defaults they were already using; new stores get it when they are set up;
 * setting up again keeps what staff changed. A missing row is a bug: reads
 * fail loudly instead of filling in defaults.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('returns-store', ['country_code' => 'NG']);
    $this->owner = $this->store->run(static function (): StaffMember {
        $owner = StaffMember::factory()->create();
        $owner->assignRole(StaffRole::Owner->value);

        return $owner;
    });
});

afterEach(function (): void {
    deleteAllStores();
});

it('gives a store set up before the migration its row, from the defaults it was using, and keeps a row that exists', function (): void {
    $migration = require database_path('migrations/tenant/2026_11_07_100000_create_return_settings_for_every_store.php');

    [$created, $kept] = $this->store->run(static function () use ($migration): array {
        // A store as it was before: no row.
        DB::table('return_settings')->delete();
        $migration->up();
        $created = ReturnSettings::current()->only(['window_days', 'customer_requests', 'photos_required']);

        // A store whose staff changed theirs keeps them.
        ReturnSettings::query()->whereKey(1)->update(['window_days' => 30]);
        $migration->up();

        return [$created, ReturnSettings::current()->window_days];
    });

    expect($created)->toBe(['window_days' => 14, 'customer_requests' => true, 'photos_required' => false])
        ->and($kept)->toBe(30);
});

it('gives a new store its row when it is set up, and setting it up again keeps what staff changed', function (): void {
    config(['returns.window_days' => 21]);
    $store = createStore('new-store', ['country_code' => 'NG']);

    [$fresh, $again] = $store->run(static function (): array {
        // The template copy has the migration's row; setting the store up adds none and changes none.
        DB::table('return_settings')->delete();
        app(StoreSettingsSetup::class)->initialize();
        $fresh = [ReturnSettings::query()->count(), ReturnSettings::current()->window_days];

        app(ChangeReturnSettings::class)->handle(windowDays: 45);
        app(StoreSettingsSetup::class)->initialize();

        return [$fresh, ReturnSettings::current()->window_days];
    });

    expect($fresh)->toBe([1, 21])
        ->and($again)->toBe(45);
});

it('fails loudly when the row is missing, never filling in defaults', function (): void {
    $this->store->run(static function (): void {
        DB::table('return_settings')->delete();

        expect(fn () => ReturnSettings::current())->toThrow(MissingReturnSettingsException::class)
            ->and(fn () => app(ChangeReturnSettings::class)->handle(windowDays: 30))->toThrow(MissingReturnSettingsException::class)
            ->and(ReturnSettings::query()->count())->toBe(0);
    });

    returnsRequest('GET', 'settings', as: $this->owner, subdomain: 'returns-store')->assertServerError();
});
