<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Redige, modifica e pubblica i documenti legali accettati dai commercianti, come i termini di servizio.',
    ],
    'stores' => [
        'view' => 'Visualizza ogni negozio, il suo stato, il piano, le concessioni di funzionalità e le modifiche dei limiti.',
        'manage' => 'Sospendi, riattiva, chiudi e ripristina i negozi e riprova a configurare un negozio la cui configurazione non è riuscita.',
        'grant' => 'Fornisci ai negozi moduli e integrazioni al di fuori del loro piano e modifica i limiti di utilizzo.',
        'export' => 'Esporta tutti i dati di un negozio, inclusi i dati personali di ogni cliente.',
    ],
    'staff' => [
        'view' => 'Guarda il team: i membri dello staff, i loro ruoli e gli inviti in sospeso.',
        'invite' => 'Invita le persone a unirsi al team e invia nuovamente o annulla gli inviti.',
        'manage' => 'Modificare i ruoli dei membri dello staff e disattivarli o riattivarli.',
    ],
    'catalog' => [
        'view' => 'Consulta il catalogo: marchi, categorie e prodotti, comprese bozze, articoli archiviati e cestino.',
        'manage' => 'Crea e modifica marchi, categorie e prodotti, spostali nel cestino e ripristinali.',
    ],
    'inventory' => [
        'view' => 'Visualizza i livelli delle scorte, le ubicazioni e la cronologia di ogni variazione delle scorte.',
        'adjust' => 'Ricevi scorte, correggi i conteggi delle scorte e modifica la modalità di gestione delle scorte di ciascuna variante.',
        'costs' => 'Visualizza e modifica il costo di ciascuna unità per il negozio. I costi sono interni e non vengono mai mostrati ai clienti.',
    ],
    'customers' => [
        'view' => 'Visualizza i clienti, i loro profili, indirizzi, note dello staff e gruppi, inclusi gli account disattivati ​​e cestinati.',
        'manage' => 'Modifica i profili dei clienti, disattiva, riattiva, elimina e ripristina gli account, aggiungi o elimina note e gestisci gruppi di clienti.',
        'tax_exemption' => 'Rendere un cliente esentasse o interromperlo dopo aver controllato il suo codice fiscale.',
        'privacy' => 'Scarica tutti i dati personali di un cliente o cancellali definitivamente, su richiesta del cliente.',
    ],
    'promotions' => [
        'view' => 'Visualizza le promozioni, i relativi codici e la frequenza con cui sono stati utilizzati e visualizza in anteprima cosa otterrebbe un carrello.',
        'manage' => 'Crea, modifica, attiva, disattiva e archivia promozioni e aggiungi, genera o modifica i relativi codici.',
    ],
    'shipping' => [
        'view' => 'Visualizza le zone di spedizione, i metodi e le relative tariffe e visualizza in anteprima quanto pagherebbe un carrello per la spedizione.',
        'manage' => 'Crea, modifica ed elimina zone di spedizione e crea, modifica e archivia metodi di spedizione e relative tariffe.',
    ],
    'orders' => [
        'view' => 'Visualizza gli ordini, le loro linee, i pagamenti, lo storico e le spedizioni.',
        'manage' => 'Annulla ordini, aggiungi note ed etichette e correggi i dettagli di contatto e gli indirizzi di spedizione degli acquirenti.',
        'fulfil' => 'Crea spedizioni, aggiungi tracciabilità e contrassegna ciò che è rimasto, è pronto per il ritiro o è arrivato.',
    ],
    'invoices' => [
        'view' => 'Visualizza fatture e note di credito con i dettagli dell\'acquirente e scarica i relativi PDF.',
    ],
    'payments' => [
        'view' => 'Scopri pagamenti, rimborsi e come sono collegati i gateway di pagamento.',
        'confirm' => 'Conferma che sia arrivato un bonifico bancario o che sia stato riscosso il pagamento in contrassegno e chiedi nuovamente al gateway informazioni sul pagamento.',
        'refund' => 'Rimborso dei pagamenti, tramite il gateway o registrato manualmente.',
        'manage_gateways' => 'Connetti, disconnetti e imposta gateway di pagamento e metodi di pagamento.',
    ],
    'pricing' => [
        'view' => 'Visualizza le classi fiscali, le aliquote fiscali, la classe in cui si trova ciascuna variante, le valute e i tassi di cambio del negozio.',
        'manage' => 'Modifica le classi fiscali, le aliquote fiscali, le classi fiscali delle varianti, le valute del negozio, i tassi di cambio e i prezzi fissi.',
    ],
    'settings' => [
        'view' => 'Visualizza le impostazioni del negozio: nome, paese, valuta, lingue, modalità fiscale, unità e dettagli di contatto.',
        'manage' => 'Modifica le impostazioni del negozio. Richiedere l\'autenticazione a due fattori per il personale rimane con il proprietario.',
    ],
    'roles' => [
        'view' => 'Visualizza i ruoli del negozio e ciò che ciascuno consente.',
        'manage' => 'Crea, rinomina ed elimina ruoli e scegli cosa consente ciascuno.',
    ],
    'delivery' => [
        'view' => 'Vedere gli autisti e le consegne del negozio.',
        'manage' => 'Affidare spedizioni agli autisti e far avanzare, ritentare, riprendere o annullare le loro consegne.',
        'cash' => [
            'manage' => 'Registrare i contanti consegnati dagli autisti, mai più di quanto ne abbiano.',
        ],
    ],
    'drivers' => [
        'manage' => 'Aggiungere, modificare, disattivare e cancellare autisti, e dare loro codici per scegliere un PIN.',
    ],
    'returns' => [
        'view' => 'Vedere i resi, le loro foto e la loro cronologia, e le impostazioni dei resi.',
        'manage' => 'Avviare, approvare, rifiutare, ricevere, controllare e annullare i resi, e modificare le impostazioni dei resi.',
    ],
];
