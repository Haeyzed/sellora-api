<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Conferma il tuo indirizzo email',
        'reason' => 'Conferma che questo sia il tuo indirizzo email, così potrai vedere i tuoi ordini passati e scaricare i tuoi dati.',
        'action' => 'Conferma la mia email',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :hours ore.',
        'ignore' => 'Se non hai creato un account, puoi ignorare questa email.',
    ],
    'email_change' => [
        'subject' => 'Conferma il tuo nuovo indirizzo email',
        'reason' => 'Hai chiesto di utilizzare questo indirizzo per il tuo account. Apri questo collegamento dopo aver effettuato l\'accesso per apportare la modifica.',
        'action' => 'Usa questa email',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :minutes minuti.',
        'ignore' => 'Se non sei stato tu a richiederlo, puoi ignorare questa email; non cambia nulla.',
    ],
    'marketing_consent' => [
        'email' => 'Inviami notizie e offerte da :store via e-mail. Posso annullare l\'iscrizione in qualsiasi momento.',
        'sms' => 'Inviami novità e offerte da :store tramite SMS. Posso annullare l\'iscrizione in qualsiasi momento.',
    ],
    'email_changed' => [
        'subject' => 'L\'indirizzo email del tuo account è stato modificato',
        'reason' => 'L\'indirizzo email del tuo account è stato modificato e tutti gli altri dispositivi sono stati disconnessi. Le email relative al tuo account ora verranno inviate al nuovo indirizzo.',
        'help' => 'Se non hai apportato tu questa modifica, contatta subito il negozio.',
    ],
];
