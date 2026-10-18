<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Webhooks and checks with gateways are urgent work: they run on their own
    | queue, never behind bulk work such as imports and exports. Workers must
    | listen on it (docs/deployment.md).
    |
    */

    'queue' => env('PAYMENTS_QUEUE', 'payments'),

    /*
    |--------------------------------------------------------------------------
    | Checks With the Gateway
    |--------------------------------------------------------------------------
    |
    | A payment still pending is asked about on its own, without waiting for
    | a webhook: once after each of these waits, in minutes, counted from the
    | previous check. Once it has been pending longer than give_up_after_hours
    | it becomes "unresolved": staff are told, and it is still asked about
    | once every recheck_unresolved_after_hours, because money may yet arrive.
    |
    | The hourly sweep is the safety net: it asks about anything pending whose
    | last check is older than sweep_after_minutes, however that happened.
    |
    */

    'checks' => [
        'backoff_minutes' => [2, 10, 30, 60, 180, 360, 720],
        'give_up_after_hours' => (int) env('PAYMENTS_GIVE_UP_AFTER_HOURS', 24),
        'recheck_unresolved_after_hours' => (int) env('PAYMENTS_RECHECK_UNRESOLVED_AFTER_HOURS', 24),
        'sweep_after_minutes' => (int) env('PAYMENTS_SWEEP_AFTER_MINUTES', 60),
        'sweep_per_run' => (int) env('PAYMENTS_SWEEP_PER_RUN', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Endpoints
    |--------------------------------------------------------------------------
    |
    | A central webhook endpoint is created before the store saves the
    | connection naming it. One that no connection names after this long was
    | left by a save that failed, and the hourly sweep deletes it.
    |
    */

    'orphaned_endpoints_after_minutes' => (int) env('PAYMENTS_ORPHANED_ENDPOINTS_AFTER_MINUTES', 60),

];
