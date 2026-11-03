<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| For ErrorScrubbingTest.php and the tests of each path an error can escape by
|--------------------------------------------------------------------------
*/

/**
 * Inserts a customer with this email but no name into the current store, which PostgreSQL refuses: its error quotes the SQL, its values and the failing row, email included.
 */
function insertCustomerWithoutName(string $email): never
{
    DB::table('customers')->insert(['public_id' => (string) Str::ulid(), 'email' => $email, 'password' => 'not-a-hash']);

    throw new LogicException('The customer without a name was expected to be refused.');
}

/**
 * Inserts a second customer with this email into the current store, which its unique index refuses: the error names the constraint and quotes the email as the duplicate key.
 */
function insertCustomerTwice(string $email): never
{
    $customer = ['name' => 'Ada Buyer', 'email' => $email, 'password' => 'not-a-hash'];
    DB::table('customers')->insert([...$customer, 'public_id' => (string) Str::ulid()]);
    DB::table('customers')->insert([...$customer, 'public_id' => (string) Str::ulid()]);

    throw new LogicException('The second customer with the same email was expected to be refused.');
}

/**
 * The real database error refusing a customer without a name, caught, as code that reports it would hold it.
 */
function databaseErrorQuotingEmail(string $email): QueryException
{
    try {
        insertCustomerWithoutName($email);
    } catch (QueryException $exception) {
        return $exception;
    }
}

/**
 * Throws an error whose message says nothing personal from a function that was given the email, so only a stack trace that records arguments could show it.
 */
function failWhileHandlingEmail(string $email): never
{
    throw new RuntimeException('Handling the address failed.');
}

/**
 * Sends the application's log to a file of its own for this test and returns its path, so a test can read every entry in full, stack traces included.
 */
function logToTemporaryFile(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'scrubbing-log-');

    config([
        'logging.channels.scrubbing_test' => ['driver' => 'single', 'path' => $path, 'level' => 'debug'],
        'logging.default' => 'scrubbing_test',
    ]);

    return $path;
}
