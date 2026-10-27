<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Section 12: the database describes itself. Every table has a comment, and
 * so does every column nobody can read correctly without one: money (in minor
 * units), statuses (which enum), public IDs and hashes. These comments are
 * the field reference (php artisan schema:reference), so a new migration
 * without them fails here, naming what to describe.
 */
uses(TruncatesCentralDatabase::class);

afterEach(function (): void {
    deleteAllStores();
});

it('describes every central table and its key columns', function (): void {
    $missing = uncommentedSchema(DB::connection(config()->string('tenancy.database.central_connection')));

    expect($missing)->toBe([], 'Add comments in a migration for: '.implode(', ', $missing));
});

it('describes every store table and its key columns', function (): void {
    $store = createStore('described-store');

    $missing = $store->run(static fn (): array => uncommentedSchema(DB::connection('tenant')));

    expect($missing)->toBe([], 'Add comments in a migration for: '.implode(', ', $missing));
});
