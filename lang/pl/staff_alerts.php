<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Nowe zamówienie :order',
        'message' => 'Złożono zamówienie :order na kwotę :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Płatność za zamówienie :order wymaga sprawdzenia',
        'message' => 'Płatność :amount za zamówienie :order nie zgadzała się z oczekiwaną. Sprawdź ją przed realizacją zamówienia.',
    ],
    'payment_unresolved' => [
        'title' => 'Płatność za zamówienie :order jest nierozstrzygnięta',
        'message' => 'Bramka płatności nie rozliczyła płatności :amount za zamówienie :order. Sprawdź ją w bramce.',
    ],
    'payment_overpaid' => [
        'title' => 'Zamówienie :order zostało nadpłacone',
        'message' => 'Kupujący zapłacił :amount więcej, niż kosztuje zamówienie :order. Zwróć różnicę.',
    ],
    'payment_disputed' => [
        'title' => 'Płatność została zakwestionowana',
        'message' => 'Kupujący zakwestionował płatność dokonaną przez :gateway. Odpowiedz na reklamację w panelu bramki przed terminem.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Połącz ponownie :gateway',
        'message' => ':gateway odrzuciła klucze sklepu. Płatności przez nią czekają, dopóki jej ponownie nie połączysz.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Anulowane zamówienie :order zostało opłacone',
        'message' => 'Po anulowaniu zamówienia :order wpłynęła za nie płatność. Zamówienie pozostaje anulowane; zwróć płatność.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Za zamówienie :order zwrócono więcej, niż zapłacono',
        'message' => 'Zwroty za zamówienie :order przekraczają kwotę zapłaconą przez kupującego. Sprawdź zwroty zamówienia.',
    ],
    'refund_needs_review' => [
        'title' => 'Zwrot za zamówienie :order wymaga sprawdzenia',
        'message' => 'Zwrot :amount za zamówienie :order wymaga sprawdzenia: odpowiedź bramki była niejasna.',
    ],
    'refund_failed' => [
        'title' => 'Zwrot za zamówienie :order nie powiódł się',
        'message' => 'Zwrot :amount za zamówienie :order nie powiódł się. Zwróć pieniądze kupującemu w inny sposób lub spróbuj ponownie.',
    ],
    'refund_still_pending' => [
        'title' => 'Zwrot za zamówienie :order wciąż oczekuje',
        'message' => 'Zwrot :amount za zamówienie :order od kilku dni czeka w bramce. Sprawdź go w bramce.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Nieudany zwrot za zamówienie :order jednak się powiódł',
        'message' => 'Zwrot :amount za zamówienie :order, zgłoszony jako nieudany, ostatecznie się powiódł. Jeśli zwrócono ponownie, kupujący otrzymał zwrot dwa razy.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Nie można rozliczyć płatności za zamówienie :order',
        'message' => 'Rozliczenie płatności :method w wysokości :amount za zamówienie :order wciąż się nie udaje. Jest ponawiane co godzinę.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Nie można zapisać płatności zamówienia :order',
        'message' => 'Zapisywanie płatności zamówienia :order wciąż się nie udaje. Jest ponawiane co godzinę.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Nie można zwrócić pieniędzy za anulowane zamówienie :order',
        'message' => 'Nie udało się zwrócić płatności :method w wysokości :amount za anulowane zamówienie :order. Jest ponawiane co godzinę, a zamówienie wciąż wymaga zwrotu.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Wymiana dla zamówienia :order nie została rozliczona',
        'message' => 'Zamówienie zastępcze :order zostało opłacone, ale rozliczenie jego zwrotu odrzucono (:reason). Rozlicz je ponownie ze zwrotu.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Gotówka za zamówienie :order nie została zapisana',
        'message' => 'Zamówienie :order zostało dostarczone, ale :amount w gotówce nie zapisano jako pobranych.',
    ],
    'delivery_failed' => [
        'title' => 'Dostawa zamówienia :order nie powiodła się',
        'message' => 'Kierowca nie mógł dostarczyć zamówienia :order. Zorganizuj kolejną dostawę lub skontaktuj się z kupującym.',
    ],
    'stock_running_low' => [
        'title' => 'Kończy się :product',
        'message' => 'Zostało tylko :available szt. produktu :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product jest wyprzedany',
        'message' => ':product (SKU :sku) jest wyprzedany.',
    ],
    'return_requested' => [
        'title' => 'Prośba o zwrot dla zamówienia :order',
        'message' => 'Kupujący poprosił o zwrot produktów z zamówienia :order.',
    ],
    'waiting' => [
        'title' => 'Czekają kolejne powiadomienia',
        'message' => 'W tej godzinie przyszło więcej powiadomień, niż jest wysyłanych e-mailem. Zobacz je wszystkie w panelu.',
    ],
    'see_dashboard' => 'Zobacz je w panelu swojego sklepu.',
];
