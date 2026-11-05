<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Gestisci documenti legali',
            'description' => 'Redige, modifica e pubblica i documenti legali accettati dai commercianti, come i termini di servizio.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Visualizza negozi',
            'description' => 'Visualizza ogni negozio, il suo stato, il piano, le concessioni di funzionalità e le modifiche dei limiti.',
        ],
        'manage' => [
            'label' => 'Gestisci negozi',
            'description' => 'Sospendi, riattiva, chiudi e ripristina i negozi e riprova a configurare un negozio la cui configurazione non è riuscita.',
        ],
        'grant' => [
            'label' => 'Concedi funzionalità e limiti',
            'description' => 'Fornisci ai negozi moduli e integrazioni al di fuori del loro piano e modifica i limiti di utilizzo.',
        ],
        'export' => [
            'label' => 'Esporta dati dei negozi',
            'description' => 'Esporta tutti i dati di un negozio, inclusi i dati personali di ogni cliente.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Visualizza team',
            'description' => 'Guarda il team: i membri dello staff, i loro ruoli e gli inviti in sospeso.',
        ],
        'invite' => [
            'label' => 'Invita personale',
            'description' => 'Invita le persone a unirsi al team e invia nuovamente o annulla gli inviti.',
        ],
        'manage' => [
            'label' => 'Gestisci personale',
            'description' => 'Modificare i ruoli dei membri dello staff e disattivarli o riattivarli.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Visualizza catalogo',
            'description' => 'Consulta il catalogo: marchi, categorie e prodotti, comprese bozze, articoli archiviati e cestino.',
        ],
        'manage' => [
            'label' => 'Gestisci catalogo',
            'description' => 'Crea e modifica marchi, categorie e prodotti, spostali nel cestino e ripristinali.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Visualizza magazzino',
            'description' => 'Visualizza i livelli delle scorte, le ubicazioni e la cronologia di ogni variazione delle scorte.',
        ],
        'adjust' => [
            'label' => 'Rettifica magazzino',
            'description' => 'Ricevi scorte, correggi i conteggi delle scorte e modifica la modalità di gestione delle scorte di ciascuna variante.',
        ],
        'costs' => [
            'label' => 'Costi unitari',
            'description' => 'Visualizza e modifica il costo di ciascuna unità per il negozio. I costi sono interni e non vengono mai mostrati ai clienti.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Visualizza clienti',
            'description' => 'Visualizza i clienti, i loro profili, indirizzi, note dello staff e gruppi, inclusi gli account disattivati ​​e cestinati.',
        ],
        'manage' => [
            'label' => 'Gestisci clienti',
            'description' => 'Modifica i profili dei clienti, disattiva, riattiva, elimina e ripristina gli account, aggiungi o elimina note e gestisci gruppi di clienti.',
        ],
        'tax_exemption' => [
            'label' => 'Esenzioni fiscali',
            'description' => 'Rendere un cliente esentasse o interromperlo dopo aver controllato il suo codice fiscale.',
        ],
        'privacy' => [
            'label' => 'Richieste di dati dei clienti',
            'description' => 'Scarica tutti i dati personali di un cliente o cancellali definitivamente, su richiesta del cliente.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Visualizza promozioni',
            'description' => 'Visualizza le promozioni, i relativi codici e la frequenza con cui sono stati utilizzati e visualizza in anteprima cosa otterrebbe un carrello.',
        ],
        'manage' => [
            'label' => 'Gestisci promozioni',
            'description' => 'Crea, modifica, attiva, disattiva e archivia promozioni e aggiungi, genera o modifica i relativi codici.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Visualizza spedizioni',
            'description' => 'Visualizza le zone di spedizione, i metodi e le relative tariffe e visualizza in anteprima quanto pagherebbe un carrello per la spedizione.',
        ],
        'manage' => [
            'label' => 'Gestisci spedizioni',
            'description' => 'Crea, modifica ed elimina zone di spedizione e crea, modifica e archivia metodi di spedizione e relative tariffe.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Visualizza ordini',
            'description' => 'Visualizza gli ordini, le loro linee, i pagamenti, lo storico e le spedizioni.',
        ],
        'manage' => [
            'label' => 'Gestisci ordini',
            'description' => 'Annulla ordini, aggiungi note ed etichette e correggi i dettagli di contatto e gli indirizzi di spedizione degli acquirenti.',
        ],
        'fulfil' => [
            'label' => 'Evadi ordini',
            'description' => 'Crea spedizioni, aggiungi tracciabilità e contrassegna ciò che è rimasto, è pronto per il ritiro o è arrivato.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Visualizza fatture',
            'description' => 'Visualizza fatture e note di credito con i dettagli dell\'acquirente e scarica i relativi PDF.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Visualizza pagamenti',
            'description' => 'Scopri pagamenti, rimborsi e come sono collegati i gateway di pagamento.',
        ],
        'confirm' => [
            'label' => 'Conferma pagamenti',
            'description' => 'Conferma che sia arrivato un bonifico bancario o che sia stato riscosso il pagamento in contrassegno e chiedi nuovamente al gateway informazioni sul pagamento.',
        ],
        'refund' => [
            'label' => 'Rimborsa pagamenti',
            'description' => 'Rimborso dei pagamenti, tramite il gateway o registrato manualmente.',
        ],
        'manage_gateways' => [
            'label' => 'Gestisci gateway di pagamento',
            'description' => 'Connetti, disconnetti e imposta gateway di pagamento e metodi di pagamento.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Visualizza imposte e valute',
            'description' => 'Visualizza le classi fiscali, le aliquote fiscali, la classe in cui si trova ciascuna variante, le valute e i tassi di cambio del negozio.',
        ],
        'manage' => [
            'label' => 'Gestisci imposte e valute',
            'description' => 'Modifica le classi fiscali, le aliquote fiscali, le classi fiscali delle varianti, le valute del negozio, i tassi di cambio e i prezzi fissi.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Visualizza impostazioni',
            'description' => 'Visualizza le impostazioni del negozio: nome, paese, valuta, lingue, modalità fiscale, unità e dettagli di contatto.',
        ],
        'manage' => [
            'label' => 'Gestisci impostazioni',
            'description' => 'Modifica le impostazioni del negozio. Richiedere l\'autenticazione a due fattori per il personale rimane con il proprietario.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Visualizza ruoli',
            'description' => 'Visualizza i ruoli del negozio e ciò che ciascuno consente.',
        ],
        'manage' => [
            'label' => 'Gestisci ruoli',
            'description' => 'Crea, rinomina ed elimina ruoli e scegli cosa consente ciascuno.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Visualizza consegne',
            'description' => 'Vedere gli autisti e le consegne del negozio.',
        ],
        'manage' => [
            'label' => 'Gestisci consegne',
            'description' => 'Affidare spedizioni agli autisti e far avanzare, ritentare, riprendere o annullare le loro consegne.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Registra contanti dei corrieri',
                'description' => 'Registrare i contanti consegnati dagli autisti, mai più di quanto ne abbiano.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Gestisci corrieri',
            'description' => 'Aggiungere, modificare, disattivare e cancellare autisti, e dare loro codici per scegliere un PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Visualizza resi',
            'description' => 'Vedere i resi, le loro foto e la loro cronologia, e le impostazioni dei resi.',
        ],
        'manage' => [
            'label' => 'Gestisci resi',
            'description' => 'Avviare, approvare, rifiutare, ricevere, controllare e annullare i resi, e modificare le impostazioni dei resi.',
        ],
        'exchange' => [
            'label' => 'Confermare i cambi',
            'description' => 'Confermare il cambio di un reso: crea il suo ordine sostitutivo e regola il valore degli articoli resi.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Visualizzare i contenuti della vetrina',
            'description' => 'Vedere le pagine, i menu, i banner e le impostazioni della vetrina del negozio.',
        ],
        'manage' => [
            'label' => 'Gestire i contenuti della vetrina',
            'description' => 'Aggiungere, modificare, pubblicare e rimuovere le pagine, i menu e i banner del negozio, e modificare le impostazioni della vetrina.',
        ],
    ],
];
