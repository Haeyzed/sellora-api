<?php

declare(strict_types=1);

return [

    /*
    | Codes staff generate: characters from an alphabet without look-alikes
    | (no 0, O, 1 or I), 12 by default, which is about 60 bits each, so
    | guessing one is hopeless within the per-requester rate limit.
    */
    'generated_codes' => [
        'default_length' => 12,
        'min_length' => 8,
        'max_length' => 20,
        // Up to this many are made during the request; more are made on the bulk queue.
        'made_at_once' => (int) env('PROMOTIONS_CODES_MADE_AT_ONCE', 1000),
        'max_per_request' => 100000,
    ],

    /*
    | A store-wide count of codes entered that weren't valid, per hour. Past
    | the threshold a warning is logged (without the codes tried) for whoever
    | watches for guessing; shoppers are never affected by it.
    */
    'failed_code_attempts_warning_per_hour' => (int) env('PROMOTIONS_FAILED_CODE_ATTEMPTS_WARNING', 300),

];
