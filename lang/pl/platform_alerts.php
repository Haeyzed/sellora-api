<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Kursy walut nieodświeżone od :hours godzin',
        'message' => 'Od co najmniej :hours godzin nie udało się pobrać kursów walut. Sklepy przestają oferować dodatkowe waluty, gdy kursy mają :stale_hours godzin. Sprawdź dostawcę kursów walut.',
    ],
    'store_setup_failed' => [
        'title' => 'Konfiguracja sklepu :store nie powiodła się',
        'message' => 'Konfiguracja sklepu :store (:store_id) nie powiodła się po wszystkich próbach. Ponów ją z listy sklepów po usunięciu przyczyny.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Stany magazynowe w :store nie zgadzają się z rejestrem',
        'message' => 'Zaplanowana kontrola znalazła w sklepie :store (:store_id) stany magazynowe niezgodne z jego rejestrem ruchów magazynowych (:count). Szczegóły są w dzienniku kontroli.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Faktury w :store nie zgadzają się z zatrzymaną sprzedażą',
        'message' => 'Zaplanowana kontrola znalazła w sklepie :store (:store_id) faktury, których korekty nie zgadzają się z zatrzymanymi pieniędzmi ze sprzedaży lub zafakturowanym podatkiem (:count). Szczegóły są w dzienniku kontroli.',
    ],
    'see_dashboard' => 'Zaloguj się do panelu platformy :app, aby dowiedzieć się więcej.',
];
