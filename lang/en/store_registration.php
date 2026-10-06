<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Store Registration Emails
|--------------------------------------------------------------------------
*/

return [

    'code' => [
        'subject' => ':code is your store verification code',
        'reason' => 'Use this code to finish registering :store.',
        'code' => 'Your code: :code',
        'expiry' => 'It expires in :minutes minutes.',
        'ignore' => 'If you did not try to register a store, you can ignore this email.',
    ],

    'ready' => [
        'subject' => ':store is ready',
        'line' => 'Your store :store is set up. Sign in to add your products and start selling.',
        'action' => 'Go to your dashboard',
    ],

];
