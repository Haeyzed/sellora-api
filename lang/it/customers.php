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
    'social_link' => [
        'subject' => 'Qualcuno sta collegando un accesso al tuo account',
        'reason' => 'Qualcuno si è registrato con :provider usando questo indirizzo email e ha chiesto di collegarlo al tuo account. Conferma solo se sei stato tu: una volta collegato, accedere con :provider apre il tuo account.',
        'action' => 'Sì, collega :provider',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :minutes minuti.',
        'ignore' => 'Se non sei stato tu, ignora questa email: nulla è collegato e il tuo account resta invariato.',
    ],
    'erasure' => [
        'subject' => 'Conferma la cancellazione del tuo account',
        'reason' => 'Hai chiesto di cancellare il tuo account in questo negozio. Apri questo link mentre sei loggato per confermare: tutto ciò che il negozio tiene di te viene eliminato e non può essere annullato.',
        'action' => 'Elimina il mio account',
        'expiry' => 'Questo collegamento funziona una volta e scade tra :minutes minuti.',
        'ignore' => 'Se non sei stato tu a richiederlo, ignora questa email: nulla viene cancellato. Un’altra persona potrebbe essere collegata al tuo account, quindi disconnettiti dagli altri dispositivi.',
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
