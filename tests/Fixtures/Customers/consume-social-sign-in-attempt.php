<?php

declare(strict_types=1);

/*
 * Uses up a social sign-in's state in a store from a separate PHP process,
 * as the storefront's callback reaching another server at the same moment
 * would, for SocialSignInTest. Waits until the given moment so the processes
 * overlap, then prints "used" or "refused".
 *
 * Usage: php consume-social-sign-in-attempt.php <central database> <store id> <provider> <state> <start at (unix time)>
 */

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Customers\Enums\SocialProvider;
use App\Tenant\Customers\Enums\SocialSignInIntent;
use App\Tenant\Customers\Exceptions\InvalidSocialSignInException;
use App\Tenant\Customers\Services\SocialSignInAttempts;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $centralDatabase, $storeId, $provider, $state, $startAt] = $argv;
config(['database.connections.'.config()->string('database.default').'.database' => $centralDatabase]);
DB::purge();

Tenant::query()->findOrFail($storeId)->run(static function () use ($provider, $state, $startAt): void {
    DB::connection()->getPdo();

    while (microtime(true) < (float) $startAt) {
        usleep(1_000);
    }

    try {
        app(SocialSignInAttempts::class)->consume(SocialProvider::from($provider), $state, SocialSignInIntent::SignIn);
        echo 'used', PHP_EOL;
    } catch (InvalidSocialSignInException) {
        echo 'refused', PHP_EOL;
    }
});
