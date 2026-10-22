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

];
