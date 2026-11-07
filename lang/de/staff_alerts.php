<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Neue Bestellung :order',
        'message' => 'Bestellung :order wurde über :amount aufgegeben.',
    ],
    'payment_needs_review' => [
        'title' => 'Eine Zahlung für Bestellung :order muss geprüft werden',
        'message' => 'Eine Zahlung von :amount für Bestellung :order entsprach nicht dem Erwarteten. Prüfen Sie sie, bevor Sie die Bestellung ausführen.',
    ],
    'payment_unresolved' => [
        'title' => 'Eine Zahlung für Bestellung :order ist ungeklärt',
        'message' => 'Der Zahlungsanbieter hat die Zahlung von :amount für Bestellung :order nicht abgeschlossen. Prüfen Sie sie beim Anbieter.',
    ],
    'payment_overpaid' => [
        'title' => 'Bestellung :order wurde überbezahlt',
        'message' => 'Der Käufer hat :amount mehr bezahlt, als Bestellung :order kostet. Erstatten Sie die Differenz.',
    ],
    'payment_disputed' => [
        'title' => 'Eine Zahlung wurde angefochten',
        'message' => 'Ein Käufer hat eine Zahlung über :gateway angefochten. Beantworten Sie die Anfechtung im Dashboard des Anbieters vor Ablauf der Frist.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => ':gateway neu verbinden',
        'message' => ':gateway hat die Schlüssel des Shops abgelehnt. Zahlungen darüber bleiben ausstehend, bis Sie es neu verbinden.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Stornierte Bestellung :order wurde bezahlt',
        'message' => 'Für Bestellung :order ist nach ihrer Stornierung eine Zahlung eingegangen. Die Bestellung bleibt storniert; erstatten Sie die Zahlung.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Für Bestellung :order wurde mehr erstattet als bezahlt',
        'message' => 'Die Erstattungen für Bestellung :order übersteigen, was der Käufer bezahlt hat. Prüfen Sie die Erstattungen der Bestellung.',
    ],
    'refund_needs_review' => [
        'title' => 'Eine Erstattung für Bestellung :order muss geprüft werden',
        'message' => 'Die Erstattung von :amount für Bestellung :order muss geprüft werden: Die Antwort des Anbieters war unklar.',
    ],
    'refund_failed' => [
        'title' => 'Eine Erstattung für Bestellung :order ist fehlgeschlagen',
        'message' => 'Die Erstattung von :amount für Bestellung :order ist fehlgeschlagen. Erstatten Sie dem Käufer auf anderem Weg oder versuchen Sie es erneut.',
    ],
    'refund_still_pending' => [
        'title' => 'Eine Erstattung für Bestellung :order ist noch ausstehend',
        'message' => 'Die Erstattung von :amount für Bestellung :order wartet seit Tagen beim Anbieter. Prüfen Sie sie beim Anbieter.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Eine fehlgeschlagene Erstattung für Bestellung :order ist durchgegangen',
        'message' => 'Die als fehlgeschlagen gemeldete Erstattung von :amount für Bestellung :order ist doch durchgegangen. Wenn Sie erneut erstattet haben, hat der Käufer doppelt erhalten.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Eine Zahlung für Bestellung :order kann nicht abgeschlossen werden',
        'message' => 'Der Abschluss der :method-Zahlung von :amount für Bestellung :order schlägt immer wieder fehl. Er wird stündlich erneut versucht.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Die Zahlungen von Bestellung :order können nicht erfasst werden',
        'message' => 'Das Erfassen der Zahlungen von Bestellung :order schlägt immer wieder fehl. Es wird stündlich erneut versucht.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Geld für die stornierte Bestellung :order kann nicht zurückgegeben werden',
        'message' => 'Die :method-Zahlung von :amount für die stornierte Bestellung :order konnte nicht zurückgegeben werden. Es wird stündlich erneut versucht, und die Bestellung schuldet die Erstattung weiterhin.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Ein Umtausch für Bestellung :order wurde nicht abgerechnet',
        'message' => 'Die Ersatzbestellung :order wurde bezahlt, aber die Abrechnung ihrer Rücksendung wurde abgelehnt (:reason). Rechnen Sie sie über die Rücksendung erneut ab.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Bargeld für Bestellung :order ist nicht erfasst',
        'message' => 'Bestellung :order wurde zugestellt, aber die :amount in bar wurden nicht als kassiert erfasst.',
    ],
    'delivery_failed' => [
        'title' => 'Eine Zustellung für Bestellung :order ist fehlgeschlagen',
        'message' => 'Der Fahrer konnte Bestellung :order nicht zustellen. Planen Sie eine neue Zustellung oder kontaktieren Sie den Käufer.',
    ],
    'stock_running_low' => [
        'title' => ':product wird knapp',
        'message' => 'Nur noch :available von :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product ist ausverkauft',
        'message' => ':product (SKU :sku) ist ausverkauft.',
    ],
    'return_requested' => [
        'title' => 'Rücksendung für Bestellung :order angefragt',
        'message' => 'Ein Käufer möchte Artikel aus Bestellung :order zurücksenden.',
    ],
    'waiting' => [
        'title' => 'Weitere Hinweise warten',
        'message' => 'In dieser Stunde sind mehr Hinweise eingegangen, als per E-Mail versendet werden. Sie finden alle in Ihrem Dashboard.',
    ],
    'see_dashboard' => 'Sie finden ihn im Dashboard Ihres Shops.',
];
