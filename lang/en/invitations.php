<?php

declare(strict_types=1);

return [

    'staff' => [
        'subject' => ':inviter invited you to join their store\'s team',
        'reason' => ':inviter invited you to help run their store. Accept to choose your password and sign in.',
        'action' => 'Accept the invitation',
        'expiry' => 'This link works once and expires in :days days.',
        'ignore' => 'If you weren\'t expecting this, you can ignore this email; no account is created.',
    ],

    'platform_admin' => [
        'subject' => ':inviter invited you to join the Sellora team',
        'reason' => ':inviter invited you to help run the Sellora platform. Accept to choose your password.',
        'action' => 'Accept the invitation',
        'expiry' => 'This link works once and expires in :days days.',
        'two_factor' => 'You will need an authenticator app: two-factor authentication is required before you can use your account.',
        'ignore' => 'If you weren\'t expecting this, you can ignore this email; no account is created.',
    ],

];
