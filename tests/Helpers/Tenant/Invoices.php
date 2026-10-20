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
 * Starts a web server in its own process that answers every request with 404 and logs its URL, and returns its address and a function giving the URLs asked for so far. It stops when the test ends.
 *
 * @return array{string, Closure(): list<string>}
 */
function recordingServer(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');

    if ($socket === false) {
        throw new RuntimeException('No free port for the recording server.');
    }

    // A free port, given back for the server to take.
    $address = (string) stream_socket_get_name($socket, false);
    fclose($socket);
    $log = (string) tempnam(sys_get_temp_dir(), 'requests');
    $server = new Process([PHP_BINARY, '-S', $address, base_path('tests/Fixtures/Orders/record-requests.php')], null, ['REQUEST_LOG' => $log]);
    $server->start();
    test()->beforeApplicationDestroyed(static function () use ($server, $log): void {
        $server->stop(0);
        @unlink($log);
    });

    $answers = static fn (): bool => @file_get_contents('http://'.$address.'/ready', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 1]])) !== false;

    for ($tries = 0; ! $answers(); $tries++) {
        if ($tries === 50) {
            throw new RuntimeException('The recording server did not start: '.$server->getErrorOutput());
        }

        usleep(100_000);
    }

    file_put_contents($log, '');

    return ['http://'.$address, static fn (): array => array_values(array_filter(explode(PHP_EOL, (string) file_get_contents($log))))];
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
        'issued_at' => CarbonImmutable::now(), 'paid_at' => CarbonImmutable::now(), 'issued_on' => CarbonImmutable::now()->toDateString(), 'paid_on' => CarbonImmutable::now()->toDateString(), 'locale' => 'en', 'currency' => 'NGN', 'base_currency' => 'NGN', 'exchange_rate' => 1,
        'tax_mode' => 'inclusive', 'tax_country_code' => 'NG', 'seller_name' => 'Ada', 'seller_trading_name' => 'Ada', 'subtotal_amount' => 0, 'discount_amount' => 0,
        'shipping_amount' => 0, 'shipping_discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'base_total_amount' => 0, 'created_at' => CarbonImmutable::now(),
        ...$overrides,
    ];
}
