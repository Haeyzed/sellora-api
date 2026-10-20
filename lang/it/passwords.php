<?php

declare(strict_types=1);

return [
    'reset' => 'La tua password è stata reimpostata.',
    'sent' => 'Ti abbiamo inviato via email il link per la reimpostazione della password.',
    'throttled' => 'Si prega di attendere prima di riprovare.',
    'token' => 'Questo token di reimpostazione della password non è valido.',
    'user' => 'Non riusciamo a trovare un utente con quell\'indirizzo email.',
    'notification' => [
        'subject' => 'Reimposta la tua password :app',
        'reason' => 'Hai ricevuto questa email perché abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account.',
        'action' => 'Scegli una nuova password',
        'expiry' => 'Questo collegamento scade tra :minutes minuti.',
        'ignore' => 'Se non hai chiesto tu di reimpostare la password, puoi ignorare questa email; la tua password rimane la stessa.',
    ],
];
