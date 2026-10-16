<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Order Numbers
    |--------------------------------------------------------------------------
    |
    | Each store numbers its orders from this number, shown as "#1001". Read
    | when a store's database is created; changing it later doesn't renumber
    | anything. Numbers may have gaps (an order that failed to save), and are
    | only ever shown, never used to find an order.
    |
    */

    'first_number' => (int) env('ORDERS_FIRST_NUMBER', 1001),

    /*
    |--------------------------------------------------------------------------
    | Payment Deadlines
    |--------------------------------------------------------------------------
    |
    | Every unpaid order has a deadline, so none holds stock forever. Checkout
    | gives a longer one for payment methods paid later, such as a bank
    | transfer, never longer than the maximum. Unpaid orders past their
    | deadline are cancelled by the scheduled sweep, a batch at a time.
    |
    */

    'unpaid_expiry_minutes' => (int) env('ORDERS_UNPAID_EXPIRY_MINUTES', 60),

    'max_payment_window_minutes' => (int) env('ORDERS_MAX_PAYMENT_WINDOW_MINUTES', 10080),

    'expired_orders_per_run' => (int) env('ORDERS_EXPIRED_PER_RUN', 200),

];
