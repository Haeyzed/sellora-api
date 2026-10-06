<?php

declare(strict_types=1);

/*
| Emails to the owner of a closed store (Landlord\Tenancy).
*/

return [

    'closed' => [
        'subject' => 'Your store :store is closed',
        'line' => 'Your store :store is closed. It no longer opens for anyone, and everyone was signed out. Its data will be deleted for good on :date.',
    ],

    'reminder' => [
        'subject' => 'Your store :store will be deleted on :date',
        'line' => 'Your store :store was closed. Its data will be deleted for good on :date, and can\'t be recovered after that.',
    ],

    'export' => 'If you need a copy of your store\'s data, or want to reopen the store, contact Sellora support before :date: they can restore the store so you can export everything.',
    'not_you' => 'If you didn\'t close this store, contact Sellora support straight away.',

];
