<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Wechselkurse seit :hours Stunden nicht aktualisiert',
        'message' => 'Seit mindestens :hours Stunden ist kein Abruf der Wechselkurse gelungen. Shops bieten ihre zusätzlichen Währungen nicht mehr an, sobald die Kurse :stale_hours Stunden alt sind. Prüfen Sie den Wechselkursanbieter.',
    ],
    'store_setup_failed' => [
        'title' => 'Die Einrichtung des Shops :store ist fehlgeschlagen',
        'message' => 'Die Einrichtung des Shops :store (:store_id) ist nach allen Wiederholungen fehlgeschlagen. Starten Sie sie in der Shop-Liste erneut, sobald die Ursache behoben ist.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Bestände in :store stimmen nicht mit ihrem Journal überein',
        'message' => 'Die geplante Prüfung hat im Shop :store (:store_id) Bestände gefunden, die nicht mit seinem Journal der Lagerbewegungen übereinstimmen (:count). Die Einzelheiten stehen im Protokoll der Prüfung.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Rechnungen in :store stimmen nicht mit den einbehaltenen Verkäufen überein',
        'message' => 'Die geplante Prüfung hat im Shop :store (:store_id) Rechnungen gefunden, deren Gutschriften nicht zum einbehaltenen Verkaufsgeld oder zur berechneten Steuer passen (:count). Die Einzelheiten stehen im Protokoll der Prüfung.',
    ],
    'see_dashboard' => 'Melden Sie sich im Plattform-Dashboard von :app an, um mehr zu erfahren.',
];
