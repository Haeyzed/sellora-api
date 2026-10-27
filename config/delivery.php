<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver PIN Setup Codes
    |--------------------------------------------------------------------------
    |
    | Staff never set or know a driver's PIN. They issue a one-time code,
    | shown once, and the driver chooses their own PIN with it. A code stops
    | working after this many hours, or after this many wrong tries.
    |
    */

    'setup_codes' => [
        'valid_for_hours' => (int) env('DELIVERY_SETUP_CODE_VALID_FOR_HOURS', 72),
        'max_failed_attempts' => (int) env('DELIVERY_SETUP_CODE_MAX_FAILED_ATTEMPTS', 5),
    ],

];
