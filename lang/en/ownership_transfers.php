<?php

declare(strict_types=1);

/*
| Emails about handing a store to a new owner (Tenant\Identity).
*/

return [

    'offer' => [
        'subject' => ':owner wants to make you the owner of their store',
        'reason' => ':owner has asked you to take over as the owner of the store. As owner you can do everything in the store, and the store\'s contract with Sellora becomes yours.',
        'terms' => 'To accept, sign in to the store dashboard and accept Sellora\'s current terms of service. Nothing changes until you do.',
        'action' => 'Review the transfer',
        'expiry' => 'This offer expires in :hours hours.',
        'ignore' => 'If you don\'t want to take over the store, ignore this email.',
    ],

    'completed' => [
        'subject' => 'The store :store has a new owner',
        'new_owner' => 'You are now the owner of the store :store.',
        'previous_owner' => ':owner is now the owner of the store :store. You keep only the roles you chose when you started the transfer.',
        'not_you' => 'If you didn\'t expect this, contact Sellora support straight away.',
    ],

];
