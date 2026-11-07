<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Nuovo ordine :order',
        'message' => 'È stato effettuato l\'ordine :order per :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Un pagamento dell\'ordine :order va verificato',
        'message' => 'Un pagamento di :amount per l\'ordine :order non corrispondeva a quanto previsto. Verificalo prima di evadere l\'ordine.',
    ],
    'payment_unresolved' => [
        'title' => 'Un pagamento dell\'ordine :order non è risolto',
        'message' => 'Il gateway non ha completato il pagamento di :amount per l\'ordine :order. Verificalo con il gateway.',
    ],
    'payment_overpaid' => [
        'title' => 'L\'ordine :order è stato pagato in eccesso',
        'message' => 'L\'acquirente ha pagato :amount in più del costo dell\'ordine :order. Rimborsa la differenza.',
    ],
    'payment_disputed' => [
        'title' => 'Un pagamento è stato contestato',
        'message' => 'Un acquirente ha contestato un pagamento effettuato tramite :gateway. Rispondi alla contestazione nel pannello del gateway entro la scadenza.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Ricollega :gateway',
        'message' => ':gateway ha rifiutato le chiavi del negozio. I pagamenti tramite esso restano in sospeso finché non lo ricolleghi.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'L\'ordine annullato :order è stato pagato',
        'message' => 'È arrivato un pagamento per l\'ordine :order dopo il suo annullamento. L\'ordine resta annullato; rimborsa il pagamento.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'L\'ordine :order è stato rimborsato oltre quanto pagato',
        'message' => 'I rimborsi dell\'ordine :order superano quanto pagato dall\'acquirente. Controlla i rimborsi dell\'ordine.',
    ],
    'refund_needs_review' => [
        'title' => 'Un rimborso dell\'ordine :order va verificato',
        'message' => 'Il rimborso di :amount per l\'ordine :order va verificato: la risposta del gateway non era chiara.',
    ],
    'refund_failed' => [
        'title' => 'Un rimborso dell\'ordine :order non è riuscito',
        'message' => 'Il rimborso di :amount per l\'ordine :order non è riuscito. Rimborsa l\'acquirente in un altro modo o riprova.',
    ],
    'refund_still_pending' => [
        'title' => 'Un rimborso dell\'ordine :order è ancora in sospeso',
        'message' => 'Il rimborso di :amount per l\'ordine :order attende da giorni presso il gateway. Verificalo con il gateway.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Un rimborso non riuscito dell\'ordine :order è andato a buon fine',
        'message' => 'Il rimborso di :amount per l\'ordine :order, segnalato come non riuscito, alla fine è andato a buon fine. Se hai rimborsato di nuovo, l\'acquirente è stato rimborsato due volte.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Un pagamento dell\'ordine :order non può essere completato',
        'message' => 'Il completamento del pagamento :method di :amount per l\'ordine :order continua a non riuscire. Viene ritentato ogni ora.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'I pagamenti dell\'ordine :order non possono essere registrati',
        'message' => 'La registrazione dei pagamenti dell\'ordine :order continua a non riuscire. Viene ritentata ogni ora.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Il denaro dell\'ordine annullato :order non può essere restituito',
        'message' => 'Non è stato possibile restituire il pagamento :method di :amount per l\'ordine annullato :order. Viene ritentato ogni ora e l\'ordine deve ancora il rimborso.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Un cambio dell\'ordine :order non è stato regolato',
        'message' => 'L\'ordine sostitutivo :order è stato pagato, ma la regolazione del suo reso è stata rifiutata (:reason). Regolalo di nuovo dal reso.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Il contante dell\'ordine :order non è registrato',
        'message' => 'L\'ordine :order è stato consegnato, ma i :amount in contanti non sono stati registrati come incassati.',
    ],
    'delivery_failed' => [
        'title' => 'Una consegna dell\'ordine :order non è riuscita',
        'message' => 'Il corriere non è riuscito a consegnare l\'ordine :order. Organizza un\'altra consegna o contatta l\'acquirente.',
    ],
    'stock_running_low' => [
        'title' => ':product sta per esaurirsi',
        'message' => 'Restano solo :available di :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product è esaurito',
        'message' => ':product (SKU :sku) è esaurito.',
    ],
    'return_requested' => [
        'title' => 'Reso richiesto per l\'ordine :order',
        'message' => 'Un acquirente ha chiesto di restituire articoli dell\'ordine :order.',
    ],
    'waiting' => [
        'title' => 'Altri avvisi ti aspettano',
        'message' => 'In quest\'ora sono arrivati più avvisi di quelli inviati via email. Trovali tutti nel tuo pannello.',
    ],
    'see_dashboard' => 'Trovalo nel pannello del tuo negozio.',
];
