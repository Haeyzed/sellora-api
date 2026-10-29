<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Quotes
    |--------------------------------------------------------------------------
    |
    | How long a checkout's quote can be placed as shown, in minutes. The
    | exchange rate it shows is kept for this long, so an hourly rate refresh
    | doesn't keep interrupting shoppers; anything else that changes (prices,
    | stock, promotions, shipping, tax, payment methods) still returns the new
    | quote. After it, placing returns a fresh quote to confirm.
    |
    */

    'quote_expire_minutes' => (int) env('CHECKOUT_QUOTE_EXPIRE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Giving Back What Payment Methods Took
    |--------------------------------------------------------------------------
    |
    | When a paid order is cancelled, each payment method that took money for
    | it at checkout (such as a gift card) is asked to give it back. When that
    | fails, the hourly payments sweep asks again, for this many cancelled
    | orders per store each run.
    |
    */

    'refund_retries_per_run' => (int) env('CHECKOUT_REFUND_RETRIES_PER_RUN', 100),

    /*
    |--------------------------------------------------------------------------
    | Settling What Payment Methods Hold
    |--------------------------------------------------------------------------
    |
    | What a payment method holds for an order is committed once the order is
    | paid, and released once it expires or is cancelled unpaid. When that
    | fails, the hourly payments sweep tries again, for this many orders per
    | store each run, until it settles. Staff are alerted once a hold has
    | failed this many tries in a row; it is still tried hourly after that.
    |
    */

    'hold_settle_retries_per_run' => (int) env('CHECKOUT_HOLD_SETTLE_RETRIES_PER_RUN', 100),

    'hold_settle_alert_after' => (int) env('CHECKOUT_HOLD_SETTLE_ALERT_AFTER', 3),

];
