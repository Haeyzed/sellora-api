<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",

    'notification' => [
        'subject' => 'Reset your :app password',
        'reason' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Choose a new password',
        'expiry' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not ask to reset your password, you can ignore this email; your password stays the same.',
    ],

];
