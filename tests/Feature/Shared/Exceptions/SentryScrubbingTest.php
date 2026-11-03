<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Transport\ResultStatus;
use Tests\Support\RecordingSentryTransport;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Errors reach Sentry only when a DSN is configured, and then without a
 * database error's SQL and values: scrubbed before they are reported, and
 * checked once more by before_send for anything that reaches Sentry another
 * way. Tests send nothing: they record what would leave instead.
 */

uses(TruncatesCentralDatabase::class);

afterEach(function (): void {
    RecordingSentryTransport::uninstall();
    deleteAllStores();
});

it('sends nothing to Sentry without a DSN, as in tests and on a machine with none configured', function (): void {
    expect(config('sentry.dsn'))->toBeEmpty()
        ->and(app(ClientBuilder::class)->getTransport()->send(Event::createEvent())->getStatus())->toEqual(ResultStatus::skipped());
});

it('sends a reported database error to Sentry without the email it quoted', function (): void {
    $sentry = RecordingSentryTransport::install();
    $store = createStore('scrubbing');

    // Built, never written out in this file: Sentry sends the source lines around each frame, and here those are this test's.
    $email = sprintf('%s@%s', 'ada', 'buyer.test');
    $store->run(static fn () => report(databaseErrorQuotingEmail($email)));

    expect($sentry->events)->toHaveCount(1)
        ->and((new PayloadSerializer(new Options))->serialize($sentry->events[0]))->not->toContain($email)
        ->and($sentry->events[0]->getExceptions()[0]->getValue())->toBe('database error SQLSTATE[23514] on constraint customers_erasure_check');
});

it('scrubs a database error and a breadcrumb that reach Sentry without being scrubbed first', function (): void {
    $sentry = RecordingSentryTransport::install();
    $store = createStore('scrubbing');
    // Built, never written out in this file: Sentry sends the source lines around each frame, and here those are this test's.
    $email = sprintf('%s@%s', 'ada', 'buyer.test');
    $error = $store->run(static fn (): QueryException => databaseErrorQuotingEmail($email));

    Sentry\addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_DEFAULT, 'log', $error->getMessage()));
    Sentry\captureException($error);

    $payload = (new PayloadSerializer(new Options))->serialize($sentry->events[0]);
    expect($payload)->not->toContain($email)
        ->and($payload)->not->toContain('insert into')
        ->and($sentry->events[0]->getExceptions()[0]->getValue())->toBe('database error SQLSTATE[23514] on constraint customers_erasure_check');
});

it('never sends SQL query values with breadcrumbs or traces', function (): void {
    expect(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse()
        ->and(config('sentry.tracing.sql_bindings'))->toBeFalse();
});
