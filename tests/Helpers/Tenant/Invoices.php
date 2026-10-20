<?php

declare(strict_types=1);

use App\Tenant\Orders\Data\DocumentNumber;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| For the invoice and credit note tests in tests/Feature/Tenant/Orders
|--------------------------------------------------------------------------
*/

/**
 * Takes the next number of a kind in the current store, in a transaction of its own, as issuing a document would.
 */
function takeDocumentNumber(TaxDocumentKind $kind = TaxDocumentKind::Invoice, ?CarbonImmutable $issuedAt = null): DocumentNumber
{
    return DB::transaction(static fn (): DocumentNumber => app(TaxDocumentNumbers::class)->next($kind, $issuedAt ?? CarbonImmutable::now()));
}

/**
 * Takes invoice numbers in the test store from several PHP processes at once, each taking some in turn, and returns every number taken, smallest first.
 *
 * @return list<int>
 */
function takeInvoiceNumbersInProcesses(int $processes, int $each): array
{
    $centralDatabase = DB::connection()->getDatabaseName();
    $running = [];

    for ($started = 0; $started < $processes; $started++) {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/Orders/take-invoice-numbers.php'), $centralDatabase, (string) test()->store->getTenantKey(), (string) $each]);
        $process->setTimeout(60);
        $process->start();
        $running[] = $process;
    }

    $numbers = [];

    foreach ($running as $process) {
        $process->wait();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('A process taking invoice numbers failed: '.$process->getErrorOutput().$process->getOutput());
        }

        array_push($numbers, ...array_map(intval(...), preg_split('/\R/', trim($process->getOutput())) ?: []));
    }

    sort($numbers);

    return $numbers;
}
