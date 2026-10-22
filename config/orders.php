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
    | transfer or cash on delivery (config/payments.php), never longer than
    | the maximum: 30 days, the longest a store can give cash on delivery.
    | Unpaid orders past their deadline are cancelled by the scheduled sweep,
    | a batch at a time.
    |
    */

    'unpaid_expiry_minutes' => (int) env('ORDERS_UNPAID_EXPIRY_MINUTES', 60),

    'max_payment_window_minutes' => (int) env('ORDERS_MAX_PAYMENT_WINDOW_MINUTES', 43200),

    'expired_orders_per_run' => (int) env('ORDERS_EXPIRED_PER_RUN', 200),

    /*
    |--------------------------------------------------------------------------
    | Guests' Access To Their Orders
    |--------------------------------------------------------------------------
    |
    | A guest's order comes with a token, shown once when it is placed and sent
    | in the X-Order-Token header (never a URL), that lets them see it, pay
    | again, download its invoice and credit notes, and cancel it while unpaid,
    | for this many days.
    |
    */

    'access_token_days' => (int) env('ORDERS_ACCESS_TOKEN_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Missing Tax Documents
    |--------------------------------------------------------------------------
    |
    | Each invoice and credit note is issued on the queue as soon as it falls
    | due. The hourly sweep issues any the queue missed, a batch at a time,
    | once they have been due for the grace period, so it doesn't race the
    | queue.
    |
    */

    'missing_documents_grace_minutes' => (int) env('ORDERS_MISSING_DOCUMENTS_GRACE_MINUTES', 10),

    'missing_documents_per_run' => (int) env('ORDERS_MISSING_DOCUMENTS_PER_RUN', 200),

    /*
    |--------------------------------------------------------------------------
    | Tax Document PDFs
    |--------------------------------------------------------------------------
    |
    | Each invoice and credit note is rendered to PDF once, on the default
    | queue (limited per store by api.rate_limits.tax_document_pdfs), and kept
    | on this private disk, which tenancy keeps apart per store. It is only
    | ever downloaded through the API, never served by URL.
    |
    */

    'documents_disk' => env('TAX_DOCUMENTS_DISK', 'local'),

    // mPDF's font cache and working files, shared by every store: read here, before any store's storage path applies, so fonts are prepared once rather than per store. Holds no store data once a render ends.
    'pdf_working_dir' => env('TAX_DOCUMENTS_PDF_WORKING_DIR', storage_path('framework/cache/mpdf')),

];
