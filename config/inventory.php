<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Inventory
|--------------------------------------------------------------------------
|
| How long stock is held for a customer during checkout, and how much the
| scheduled sweep gives back per store per run.
|
*/

return [

    // Minutes a checkout holds its stock before the hold runs out.
    'checkout_hold_minutes' => (int) env('INVENTORY_CHECKOUT_HOLD_MINUTES', 15),

    // Expired holds given back per store each time the sweep runs.
    'expired_reservations_per_run' => 500,

];
