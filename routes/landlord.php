<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landlord API
|--------------------------------------------------------------------------
|
| Platform admin endpoints, served on central domains only under
| /api/v1/platform and authenticated with the "platform" guard. Each file
| below belongs to one Landlord domain.
|
*/

require __DIR__.'/landlord/auth.php';
require __DIR__.'/landlord/team.php';
require __DIR__.'/landlord/tenants.php';
require __DIR__.'/landlord/plans.php';
require __DIR__.'/landlord/subscriptions.php';
require __DIR__.'/landlord/legal.php';
