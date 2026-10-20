<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter ti ha invitato a unirti al team del suo negozio',
        'reason' => ':inviter ti ha invitato ad aiutarlo a gestire il suo negozio. Accetta per scegliere la tua password ed effettuare l\'accesso.',
        'action' => 'Accetta l\'invito',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :days giorni.',
        'ignore' => 'Se non te lo aspettavi, puoi ignorare questa email; non viene creato alcun account.',
    ],
    'platform_admin' => [
        'subject' => ':inviter ti ha invitato a unirti al team Sellora',
        'reason' => ':inviter ti ha invitato ad aiutare a gestire la piattaforma Sellora. Accetta per scegliere la tua password.',
        'action' => 'Accetta l\'invito',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :days giorni.',
        'two_factor' => 'Avrai bisogno di un\'app di autenticazione: è richiesta l\'autenticazione a due fattori prima di poter utilizzare il tuo account.',
        'ignore' => 'Se non te lo aspettavi, puoi ignorare questa email; non viene creato alcun account.',
    ],
];
