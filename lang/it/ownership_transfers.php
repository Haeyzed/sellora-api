<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner vuole renderti proprietario del suo negozio',
        'reason' => ':owner ti ha chiesto di assumere il ruolo di proprietario del negozio. Come proprietario puoi fare tutto nel negozio, e il contratto del negozio con Sellora diventa tuo.',
        'terms' => 'Per accettare, accedi alla dashboard del negozio e accetta gli attuali termini di servizio di Sellora. Niente cambia finché non lo fai tu.',
        'action' => 'Rivedi il trasferimento',
        'expiry' => 'Questa offerta scade tra :hours ore.',
        'ignore' => 'Se non vuoi rilevare il negozio, ignora questa email.',
    ],
    'completed' => [
        'subject' => 'Il negozio :store ha un nuovo proprietario',
        'new_owner' => 'Ora sei il proprietario del negozio :store.',
        'previous_owner' => ':owner è ora il proprietario del negozio :store. Mantieni solo i ruoli che hai scelto quando hai iniziato il trasferimento.',
        'not_you' => 'Se non te lo aspettavi, contatta subito il supporto Sellora.',
    ],
];
