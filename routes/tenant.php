<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant API
|--------------------------------------------------------------------------
|
| Store endpoints, served on store domains only under /api/v1, after the
| store has been identified from the request's domain. Staff, customer and
| driver endpoints each use their own guard. Each file below belongs to one
| Tenant domain.
|
*/

require __DIR__.'/tenant/auth.php';
require __DIR__.'/tenant/settings.php';
require __DIR__.'/tenant/staff.php';
require __DIR__.'/tenant/customers.php';
require __DIR__.'/tenant/catalog.php';
require __DIR__.'/tenant/inventory.php';
require __DIR__.'/tenant/promotions.php';
require __DIR__.'/tenant/cart.php';
require __DIR__.'/tenant/checkout.php';
require __DIR__.'/tenant/orders.php';
require __DIR__.'/tenant/payments.php';
require __DIR__.'/tenant/shipping.php';
require __DIR__.'/tenant/delivery.php';
