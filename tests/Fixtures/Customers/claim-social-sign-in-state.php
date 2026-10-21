<?php

declare(strict_types=1);

/*
 * Claims a social sign-in state at the central relay from a separate PHP
 * process, as a browser reaching another server at the same moment would,
 * for SocialSignInRelayTest. Waits until the given moment so the processes
 * overlap, then prints the store's domain, or "none".
 *
 * Usage: php claim-social-sign-in-state.php <central database> <provider> <state> <start at (unix time)>
 */

use App\Landlord\Tenancy\Services\SocialSignInRelay;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $centralDatabase, $provider, $state, $startAt] = $argv;
config(['database.connections.'.config()->string('database.default').'.database' => $centralDatabase]);
DB::purge();
DB::connection()->getPdo();

while (microtime(true) < (float) $startAt) {
    usleep(1_000);
}

echo app(SocialSignInRelay::class)->claim($provider, $state) ?? 'none', PHP_EOL;
