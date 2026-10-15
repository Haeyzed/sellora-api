<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cart Limits
    |--------------------------------------------------------------------------
    |
    | How many different variants one cart may hold, and how many of one
    | variant a single line may ask for. Stock is checked again, exactly, when
    | checkout reserves it.
    |
    */

    'max_lines' => (int) env('CART_MAX_LINES', 100),

    'max_line_quantity' => (int) env('CART_MAX_LINE_QUANTITY', 999),

];
