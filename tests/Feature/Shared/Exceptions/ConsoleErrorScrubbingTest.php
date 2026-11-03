<?php

declare(strict_types=1);

use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Contracts\StoreStockReservations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\TruncatesCentralDatabase;

/*
 * What console commands print ends up in the terminal and the scheduler's
 * output, so a database error's email must not be in it: neither in a
 * command's own message about a store that failed, nor in what Laravel prints
 * for an error a command didn't catch.
 */

uses(TruncatesCentralDatabase::class);

afterEach(function (): void {
    deleteAllStores();
});

it('prints a store that failed in a sweep by the error\'s SQLSTATE only', function (): void {
    $store = createStore('scrubbing');
    app()->instance(StoreStockReservations::class, new class implements StoreStockReservations
    {
        public function releaseExpired(): int
        {
            insertCustomerWithoutName('ada@buyer.test');
        }
    });

    $this->artisan('inventory:release-expired-reservations')
        ->expectsOutputToContain("Store {$store->id}: database error SQLSTATE[23514] on constraint customers_erasure_check")
        ->doesntExpectOutputToContain('ada@buyer.test')
        ->assertFailed();
});

it('prints why a database server could not be reached without the driver\'s message', function (): void {
    $centralConnection = config()->array('database.connections.central');

    $this->artisan('platform:add-database-server', [
        '--name' => 'africa-2',
        '--region' => 'africa',
        '--host' => $centralConnection['host'],
        '--port' => (string) $centralConnection['port'],
        '--username' => $centralConnection['username'],
        '--capacity' => '50',
    ])
        ->expectsQuestion('Password', 'definitely-not-the-password')
        ->expectsOutputToContain('Could not connect to the server: database error SQLSTATE[')
        ->doesntExpectOutputToContain('password authentication failed')
        ->assertFailed();
});

it('prints an error a command did not catch without the email, through the console kernel as artisan runs it', function (): void {
    $store = createStore('scrubbing');
    // Not static: Laravel binds a closure command to the command.
    Artisan::command('test:crash-on-database-error', fn () => $store->run(static fn () => insertCustomerWithoutName('ada@buyer.test')));
    $output = new BufferedOutput;

    $exitCode = app(Kernel::class)->handle(new ArrayInput(['command' => 'test:crash-on-database-error']), $output);

    expect($exitCode)->not->toBe(0)
        ->and($output->fetch())->toContain('database error SQLSTATE[23514] on constraint customers_erasure_check')
        ->not->toContain('ada@buyer.test');
});

it('prints a business rule error a command did not catch without the database error that caused it', function (): void {
    $store = createStore('scrubbing');
    // Not static: Laravel binds a closure command to the command.
    Artisan::command('test:crash-on-business-rule', fn () => $store->run(static function (): never {
        try {
            insertCustomerWithoutName('ada@buyer.test');
        } catch (QueryException $exception) {
            throw new class(previous: $exception) extends DomainException
            {
                public function errorCode(): string
                {
                    return 'sample_rule_broken';
                }
            };
        }
    }));
    $output = new BufferedOutput;

    app(Kernel::class)->handle(new ArrayInput(['command' => 'test:crash-on-business-rule']), $output);

    expect($output->fetch())->not->toContain('ada@buyer.test');
});
