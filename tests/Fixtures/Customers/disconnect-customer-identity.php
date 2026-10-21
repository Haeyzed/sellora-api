<?php

declare(strict_types=1);

/*
 * Disconnects one of a customer's sign-in providers from a separate PHP
 * process, as a second device doing it at the same moment would, for
 * SocialAccountLinkingTest. Waits until the given moment so the processes
 * overlap, then prints "disconnected" or "refused".
 *
 * Usage: php disconnect-customer-identity.php <central database> <store id> <customer id> <identity id> <start at (unix time)>
 */

use App\Landlord\Tenancy\Models\Tenant;
use App\Tenant\Customers\Exceptions\LastSignInMethodException;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerIdentity;
use App\Tenant\Customers\Services\SocialAccountLinks;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $centralDatabase, $storeId, $customerId, $identityId, $startAt] = $argv;
config(['database.connections.'.config()->string('database.default').'.database' => $centralDatabase]);
// The platform's sign-in apps, as the test configured them.
config(['services.google.client_id' => 'platform-google-app', 'services.apple.client_id' => 'com.sellora.signin']);
DB::purge();

Tenant::query()->findOrFail($storeId)->run(static function () use ($customerId, $identityId, $startAt): void {
    $customer = Customer::query()->findOrFail((int) $customerId);
    $identity = CustomerIdentity::query()->findOrFail((int) $identityId);

    while (microtime(true) < (float) $startAt) {
        usleep(1_000);
    }

    try {
        app(SocialAccountLinks::class)->disconnect($customer, $identity, null);
        echo 'disconnected', PHP_EOL;
    } catch (LastSignInMethodException) {
        echo 'refused', PHP_EOL;
    }
});
