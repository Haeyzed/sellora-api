<?php

declare(strict_types=1);

/*
| Emails about store exports (sent from Landlord\Tenancy).
*/

return [

    'ready' => [
        'subject' => 'Your export of :store is ready',
        'line' => 'The export of all of :store\'s data that you asked for is ready. You can download it until :date; after that it is deleted.',
        'action' => 'Download the export',
        'signed_in' => 'Only you can download it, while signed in. If you didn\'t ask for this export, contact Sellora support straight away.',
    ],

];
