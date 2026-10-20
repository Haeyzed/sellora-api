<?php

declare(strict_types=1);

/*
 * Takes invoice numbers in a store from a separate PHP process, as requests on
 * other servers would at the same moment, for TaxDocumentNumbersTest. Each
 * number is taken in its own transaction, held open a moment so the processes
 * overlap. Prints each number taken, one per line.
 *
 * Usage: php take-invoice-numbers.php <central database> <store id> <how many>
 */

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $centralDatabase, $storeId, $count] = $argv;
config(['database.connections.'.config()->string('database.default').'.database' => $centralDatabase]);
DB::purge();

Tenant::query()->findOrFail($storeId)->run(static function () use ($count): void {
    for ($taken = 0; $taken < (int) $count; $taken++) {
        DB::transaction(static function (): void {
            echo app(TaxDocumentNumbers::class)->next(TaxDocumentKind::Invoice, CarbonImmutable::now())->sequenceNumber, PHP_EOL;
            usleep(20_000);
        });
    }
});
