<?php

declare(strict_types=1);

/*
 * One POST request, in a PHP process of its own, for the tests of requests
 * sent at the same moment (requestsAtOnce(), such as CheckoutContentionTest):
 * each process has its own database connection, as two real requests would.
 *
 * Reads a JSON file naming the request (url, headers, body), when to send it
 * (start_at, a Unix time with microseconds, so every process sends at once)
 * and the test's databases (config), handles it through the app's HTTP
 * kernel, and prints the response's status and JSON body as JSON. Runs with
 * the test run's environment.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../../vendor/autoload.php';

$spec = json_decode((string) file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$app = require __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
// Booted before the wait, so the requests really start together.
$kernel->bootstrap();

// The test's own databases (each parallel test process has its own), before anything connects.
foreach ($spec['config'] as $key => $value) {
    config([$key => $value]);
}

$app->make('db')->purge(config()->string('database.default'));

$server = ['REMOTE_ADDR' => '203.0.113.10', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

foreach ($spec['headers'] as $name => $value) {
    $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
}

$request = Request::create($spec['url'], 'POST', [], [], [], $server, json_encode($spec['body'], JSON_THROW_ON_ERROR));

// Booted and waiting: every process sends at the same moment.
while (microtime(true) < $spec['start_at']) {
    usleep(200);
}

$response = $kernel->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true)], JSON_THROW_ON_ERROR);

$kernel->terminate($request, $response);
