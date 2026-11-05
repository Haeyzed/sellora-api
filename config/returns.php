<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Returns
|--------------------------------------------------------------------------
|
| The defaults a store's return settings row is created with, when the store
| is set up (Tenant\Returns, Step 18); staff change them from there: how many days after delivery a buyer may ask to
| return a line, whether buyers may ask at all (staff always can), and
| whether a photo is required when the reason is the store's fault (damaged,
| or the wrong item). The most a store can set the window to is capped, so
| no line stays returnable forever.
|
*/

return [

    'window_days' => (int) env('RETURNS_WINDOW_DAYS', 14),

    'max_window_days' => 365,

    'customer_requests' => (bool) env('RETURNS_CUSTOMER_REQUESTS', true),

    'photos_required' => (bool) env('RETURNS_PHOTOS_REQUIRED', false),

    'max_photos' => 5,

];
