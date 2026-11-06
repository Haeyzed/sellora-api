<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messaging (Tenant\Messaging, Step 20)
|--------------------------------------------------------------------------
|
| How a store's messages are sent: on their own queue, so a burst of them
| never delays payments, and within a per-store sending limit, so one store
| can never flood the platform's mail provider. A message over the limit
| waits and is sent later; it is never dropped.
|
*/

return [

    /*
     * The queue every message waits on. Workers must listen on it
     * (docs/deployment.md).
     */
    'queue' => env('MESSAGING_QUEUE', 'messages'),

    'sending' => [
        /*
         * How many messages one store may send a minute, across every
         * channel. One over the limit waits until the minute has passed.
         */
        'per_minute' => (int) env('MESSAGING_SENDS_PER_MINUTE', 120),

        /*
         * How long a message keeps waiting for its turn (or retrying after
         * a channel failed) before it is given up as failed, in hours.
         */
        'retry_for_hours' => (int) env('MESSAGING_RETRY_FOR_HOURS', 24),
    ],

];
