<?php

declare(strict_types=1);

use App\Shared\Privacy\StoreDataExporter;
use App\Shared\Privacy\StoreExportRegistry;
use Illuminate\Support\Facades\DB;
use Tests\Support\TruncatesCentralDatabase;
use Tests\TestCase;

/*
 * Section 9.1: a store export fails closed, so a column nobody classified
 * stops every export of every store. This checks the schema the tenant
 * migrations build (a test store's database, made exactly as store setup
 * makes it) against each domain's *StoreTables classification, without
 * building an export. Unlike the other architecture tests it needs the
 * application and the database.
 */
uses(TestCase::class, TruncatesCentralDatabase::class);

afterEach(function (): void {
    deleteAllStores();
});

it('classifies every column of every store table for store exports', function (): void {
    $unclassified = createStore('classified-store')->run(static fn (): array => app(StoreExportRegistry::class)->unclassified(app(StoreDataExporter::class)->storeColumns(DB::connection())));

    expect($unclassified)->toBe([], 'Classify these store columns in their domain\'s *StoreTables class: '.implode(', ', $unclassified));
});
