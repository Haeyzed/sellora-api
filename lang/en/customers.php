<?php

declare(strict_types=1);

return [

    'email_verification' => [
        'subject' => 'Confirm your email address',
        'reason' => 'Confirm that this is your email address, so you can see your past orders and download your data.',
        'action' => 'Confirm my email',
        'expiry' => 'This link works once and expires in :hours hours.',
        'ignore' => 'If you didn\'t create an account, you can ignore this email.',
    ],

    'email_change' => [
        'subject' => 'Confirm your new email address',
        'reason' => 'You asked to use this address for your account. Open this link while signed in to make the change.',
        'action' => 'Use this email',
        'expiry' => 'This link works once and expires in :minutes minutes.',
        'ignore' => 'If you didn\'t ask for this, you can ignore this email; nothing changes.',
    ],

    /*
     * The consent text shown to customers and guests. Its exact wording, in
     * the language shown, is stored with each answer, so changing a text
     * here never changes what someone agreed to.
     */
    'marketing_consent' => [
        'email' => 'Send me news and offers from :store by email. I can unsubscribe at any time.',
        'sms' => 'Send me news and offers from :store by text message. I can unsubscribe at any time.',
    ],

    'email_changed' => [
        'subject' => 'Your account\'s email address was changed',
        'reason' => 'The email address of your account was changed, and every other device was signed out. Emails about your account now go to the new address.',
        'help' => 'If you didn\'t make this change, contact the store straight away.',
    ],

];
