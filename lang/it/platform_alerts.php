<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Tassi di cambio non aggiornati da :hours ore',
        'message' => 'Nessun recupero dei tassi di cambio è riuscito da almeno :hours ore. I negozi smettono di offrire le valute aggiuntive quando i tassi hanno :stale_hours ore. Controlla il fornitore dei tassi di cambio.',
    ],
    'store_setup_failed' => [
        'title' => 'La configurazione del negozio :store non è riuscita',
        'message' => 'La configurazione del negozio :store (:store_id) non è riuscita dopo tutti i tentativi. Riprovala dall\'elenco dei negozi una volta risolta la causa.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Le giacenze di :store non corrispondono al loro registro',
        'message' => 'Il controllo pianificato ha trovato giacenze nel negozio :store (:store_id) che non corrispondono al suo registro dei movimenti di magazzino (:count). I dettagli sono nel log del controllo.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Le fatture di :store non corrispondono alle vendite trattenute',
        'message' => 'Il controllo pianificato ha trovato fatture nel negozio :store (:store_id) le cui note di credito non tornano con il denaro delle vendite trattenuto o con l\'imposta fatturata (:count). I dettagli sono nel log del controllo.',
    ],
    'see_dashboard' => 'Accedi alla dashboard della piattaforma :app per saperne di più.',
];
