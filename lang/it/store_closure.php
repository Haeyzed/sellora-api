<?php

declare(strict_types=1);

return [
    'closed' => [
        'subject' => 'Il tuo negozio :store è chiuso',
        'line' => 'Il tuo negozio :store è chiuso. Non si apre più a nessuno e tutti sono stati disconnessi. I suoi dati verranno cancellati definitivamente il :date.',
    ],
    'reminder' => [
        'subject' => 'Il tuo negozio :store verrà eliminato il :date',
        'line' => 'Il tuo negozio :store è stato chiuso. I suoi dati verranno eliminati definitivamente il :date e successivamente non potranno più essere recuperati.',
    ],
    'export' => 'Se hai bisogno di una copia dei dati del tuo negozio, o vuoi riaprire il negozio, contatta l\'assistenza :app prima del :date: potranno ripristinare il negozio così potrai esportare tutto.',
    'not_you' => 'Se non hai chiuso tu questo negozio, contatta subito l\'assistenza :app.',
];
