<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\TruncatesCentralDatabase;

/*
 * php artisan schema:reference writes the field reference from the comments
 * migrations give each table and column: the central database as it is, and
 * the store side from a throwaway database migrated for the purpose, which
 * is always dropped.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    $this->folder = 'storage/framework/testing/schema-reference-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    File::deleteDirectory(base_path($this->folder));
});

it('writes every central and store table with the comments migrations give them, and leaves no database behind', function (): void {
    $this->artisan('schema:reference', ['--path' => $this->folder])->assertSuccessful();

    $central = File::get(base_path($this->folder.'/fields-central.md'));
    $store = File::get(base_path($this->folder.'/fields-store.md'));

    expect($central)->toContain('# Field reference: central database')
        ->toContain("## tenants\n\n".DB::selectOne("select obj_description('tenants'::regclass, 'pg_class') as comment")->comment)
        ->toContain('| `status` | character varying(24) | no | One of App\Landlord\Tenancy\Enums\TenantStatus. |')
        ->and($store)->toContain('# Field reference: store database')
        ->toContain('## orders')
        ->toContain('| `total_amount` | bigint | no | In minor units of the row\'s currency. |')
        ->not->toContain('## tenants')
        ->and(DB::select("select datname from pg_database where datname like 'schema\\_reference\\_%'"))->toBe([]);
});
