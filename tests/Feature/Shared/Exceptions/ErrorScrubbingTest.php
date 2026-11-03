<?php

declare(strict_types=1);

use App\Shared\Exceptions\ApiErrorRenderer;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\ErrorScrubber;
use App\Shared\Exceptions\ScrubbedDatabaseException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\Support\TruncatesCentralDatabase;

/*
 * A database error quotes its SQL, values and failing row, which can hold a
 * buyer's email. Wherever an error is reported or shown, the email must not
 * be in it: here the log (through report() and uncaught), the debug response
 * and the description short reasons are made from.
 */

uses(TruncatesCentralDatabase::class);

final class SampleNameTakenException extends DomainException
{
    public function errorCode(): string
    {
        return 'sample_name_taken';
    }

    public function status(): int
    {
        return 409;
    }
}

afterEach(function (): void {
    deleteAllStores();
});

it('logs a reported database error without the email it quoted, anywhere in the entry', function (): void {
    $log = logToTemporaryFile();
    $store = createStore('scrubbing');

    $store->run(static fn () => report(databaseErrorQuotingEmail('ada@buyer.test')));

    $entry = (string) file_get_contents($log);
    expect($entry)->not->toContain('ada@buyer.test')
        ->and($entry)->not->toContain('insert into')
        ->and($entry)->toContain(class_basename(ScrubbedDatabaseException::class))
        ->and($entry)->toContain('database error SQLSTATE[23514] on constraint customers_erasure_check')
        // Where it came from survives, without the arguments of each call.
        ->and($entry)->toContain('insertCustomerWithoutName');
});

it('logs an uncaught database error without the email, and answers a plain server error', function (): void {
    config(['app.debug' => false]);
    $log = logToTemporaryFile();
    $store = createStore('scrubbing');
    Route::get('/api/v1/test-errors/database', static fn () => $store->run(static fn () => insertCustomerWithoutName('ada@buyer.test')));

    $this->getJson('/api/v1/test-errors/database')
        ->assertInternalServerError()
        ->assertExactJson(['message' => __('errors.server_error'), 'code' => 'server_error', 'errors' => []]);

    expect((string) file_get_contents($log))->not->toContain('ada@buyer.test')
        ->toContain('database error SQLSTATE[23514] on constraint customers_erasure_check');
});

it('keeps the email out of the debug response too, even for a database error that reached it unscrubbed', function (): void {
    config(['app.debug' => true]);
    $store = createStore('scrubbing');
    $error = $store->run(static fn (): QueryException => databaseErrorQuotingEmail('ada@buyer.test'));

    $response = app(ApiErrorRenderer::class)->render($error);

    expect((string) $response->getContent())->not->toContain('ada@buyer.test')
        ->and(json_decode((string) $response->getContent(), true)['debug']['message'])->toBe('database error SQLSTATE[23514] on constraint customers_erasure_check');
});

it('keeps an argument a function was given out of the logged stack trace', function (): void {
    $log = logToTemporaryFile();

    try {
        failWhileHandlingEmail('ada@buyer.test');
    } catch (RuntimeException $exception) {
        report($exception);
    }

    expect((string) file_get_contents($log))->toContain('failWhileHandlingEmail')->not->toContain('ada@buyer.test');
});

it('records no function arguments in stack traces, whatever php.ini says', function (): void {
    expect(ini_get('zend.exception_ignore_args'))->toBe('1');
});

it('describes a database error by its SQLSTATE and constraint only', function (Closure $failure, string $description): void {
    $store = createStore('scrubbing');
    $error = $store->run(static function () use ($failure): QueryException {
        try {
            $failure('ada@buyer.test');
        } catch (QueryException $exception) {
            return $exception;
        }

        throw new LogicException('The insert was expected to be refused.');
    });

    expect($error->getMessage())->toContain('ada@buyer.test')
        ->and(ErrorScrubber::describe($error))->toBe($description)
        ->and(ErrorScrubber::scrub($error)->getMessage())->toBe($description);
})->with([
    'a row refused for a missing value' => [insertCustomerWithoutName(...), 'database error SQLSTATE[23514] on constraint customers_erasure_check'],
    'a duplicate refused by a unique index' => [insertCustomerTwice(...), 'database error SQLSTATE[23505] on constraint customers_email_unique'],
]);

it('scrubs an error that wraps a database error, keeping only the wrapper\'s class', function (): void {
    $store = createStore('scrubbing');
    $error = $store->run(static fn (): QueryException => databaseErrorQuotingEmail('ada@buyer.test'));
    $wrapper = new RuntimeException('Saving failed: '.$error->getMessage(), previous: $error);

    $scrubbed = ErrorScrubber::scrub($wrapper);

    expect($scrubbed->getMessage())->toBe(RuntimeException::class.' caused by database error SQLSTATE[23514] on constraint customers_erasure_check')
        ->and($scrubbed->getPrevious())->toBeNull()
        ->and((string) $scrubbed)->not->toContain('ada@buyer.test');
});

it('answers a business rule error caused by a database error with its own code, and logs nothing', function (): void {
    $log = logToTemporaryFile();
    $store = createStore('scrubbing');
    Route::get('/api/v1/test-errors/name-taken', static fn () => $store->run(static function (): never {
        try {
            insertCustomerTwice('ada@buyer.test');
        } catch (QueryException $exception) {
            throw new SampleNameTakenException(previous: $exception);
        }
    }));

    $this->getJson('/api/v1/test-errors/name-taken')
        ->assertConflict()
        ->assertJsonPath('code', 'sample_name_taken')
        ->assertDontSee('ada@buyer.test');

    expect((string) file_get_contents($log))->toBe('');
});

it('still scrubs a business rule error caused by a database error where it is printed or stored with its causes', function (): void {
    $store = createStore('scrubbing');
    $error = new SampleNameTakenException(previous: $store->run(static fn (): QueryException => databaseErrorQuotingEmail('ada@buyer.test')));

    expect(ErrorScrubber::scrub($error))->toBe($error)
        ->and(ErrorScrubber::describe($error))->toBe('sample_name_taken')
        ->and((string) ErrorScrubber::scrubForOutput($error))->not->toContain('ada@buyer.test')
        ->toContain(SampleNameTakenException::class.' caused by database error SQLSTATE[23514]');
});

it('leaves other errors as they are, and describes them by code or class only', function (): void {
    $domainError = new class extends DomainException
    {
        public function errorCode(): string
        {
            return 'sample_rule_broken';
        }
    };
    $otherError = new RuntimeException('Mail to ada@buyer.test bounced.');

    expect(ErrorScrubber::scrub($otherError))->toBe($otherError)
        ->and(ErrorScrubber::describe($domainError))->toBe('sample_rule_broken')
        ->and(ErrorScrubber::describe($otherError))->toBe(RuntimeException::class);
});
