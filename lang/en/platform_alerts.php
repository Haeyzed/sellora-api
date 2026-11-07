<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Exchange rates not refreshed for :hours hours',
        'message' => 'No exchange rate fetch has succeeded for at least :hours hours. Stores stop offering their extra currencies once rates are :stale_hours hours old. Check the exchange rate provider.',
    ],
    'store_setup_failed' => [
        'title' => 'Setting up the store :store failed',
        'message' => 'Setting up the store :store (:store_id) failed after every retry. Retry it from the store list once the cause is fixed.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Stock levels in :store don\'t match their ledger',
        'message' => 'The scheduled check found stock levels in the store :store (:store_id) that don\'t match its stock movement ledger (:count). The details are in the check\'s log.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Invoices in :store don\'t match the sales kept',
        'message' => 'The scheduled check found invoices in the store :store (:store_id) whose credit notes don\'t add up with the sale money kept or the tax invoiced (:count). The details are in the check\'s log.',
    ],
    'see_dashboard' => 'Sign in to the :app platform dashboard for more.',
];
