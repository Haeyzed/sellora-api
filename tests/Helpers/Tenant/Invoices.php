<?php

declare(strict_types=1);

use App\Tenant\Orders\Data\DocumentNumber;
use App\Tenant\Orders\Enums\TaxDocumentKind;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Services\TaxDocumentNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

/**
 * An invoices row for an order in the current store, as another request might write it straight to the database, with any columns changed. A test invoice unless changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invoiceRow(Order $order, array $overrides = []): array
{
    return [
        'public_id' => strtolower((string) Str::ulid()), 'order_id' => $order->id, 'number' => 'TEST-'.$order->number, 'series' => null, 'sequence_number' => null, 'is_test' => true,
        'issued_at' => CarbonImmutable::now(), 'paid_at' => CarbonImmutable::now(), 'locale' => 'en', 'currency' => 'NGN', 'base_currency' => 'NGN', 'exchange_rate' => 1,
        'tax_mode' => 'inclusive', 'tax_country_code' => 'NG', 'seller_name' => 'Ada', 'seller_trading_name' => 'Ada', 'subtotal_amount' => 0, 'discount_amount' => 0,
        'shipping_amount' => 0, 'shipping_discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'base_total_amount' => 0, 'created_at' => CarbonImmutable::now(),
        ...$overrides,
    ];
}
