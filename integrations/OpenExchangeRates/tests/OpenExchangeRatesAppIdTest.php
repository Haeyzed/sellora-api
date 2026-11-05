<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\RecordingSentryTransport;

/*
 * The app ID travels in a header, never in the URL, so no error, log line,
 * console output or Sentry event about a failed refresh can carry it, however
 * the request fails: refused, unreachable, or failing in a way the source
 * doesn't expect, which is reported with the request's URL in its message
 * and in Sentry's breadcrumbs.
 */

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    // Built, never written out in this file: Sentry sends the source lines around each frame, and here those are this test's.
    $this->appId = sprintf('%s-%s', 'leak-check-app-id', '7d3e');
    config(['openexchangerates.app_id' => $this->appId, 'openexchangerates.base_url' => 'https://openexchangerates.test/api/']);
    $this->sentry = RecordingSentryTransport::install();
    $this->logPath = logToTemporaryFile();
});

afterEach(function (): void {
    RecordingSentryTransport::uninstall();
});

it('never puts the app ID in a URL, error, log, console output or Sentry event when the refresh fails', function (Closure $failure, bool $isReported): void {
    // Recorded here: Laravel records only requests that got an answer, not ones that failed.
    $requestUrls = [];
    Http::fake(['*' => static function (Request $request) use ($failure, &$requestUrls) {
        $requestUrls[] = $request->url();

        return $failure($request);
    }]);
    $output = new BufferedOutput;

    $exitCode = app(Kernel::class)->handle(new ArrayInput(['command' => 'exchange-rates:refresh']), $output);

    $sentryPayloads = array_map(static fn ($event): string => (new PayloadSerializer(new Options))->serialize($event), $this->sentry->events);

    expect($exitCode)->not->toBe(0)
        ->and($requestUrls)->toBe(['https://openexchangerates.test/api/latest.json'])
        ->and(file_get_contents($this->logPath))->not->toBe('')->not->toContain($this->appId)
        ->and($output->fetch())->not->toContain($this->appId)
        ->and(implode("\n", $sentryPayloads))->not->toContain($this->appId)
        ->and(count($sentryPayloads) > 0)->toBe($isReported);
})->with([
    'refused, with the app ID quoted back' => [fn () => fn (Request $request) => Http::response('{"error":true,"status":401,"message":"invalid_app_id","description":"Invalid App ID '.config('openexchangerates.app_id').'"}', 401), false],
    'unreachable' => [fn () => fn (Request $request) => throw new ConnectionException('cURL error 6 for '.$request->url()), false],
    'failing unexpectedly, with the URL in the error' => [fn () => fn (Request $request) => throw new RuntimeException('cURL error 47: Maximum redirects followed for '.$request->url()), true],
]);
