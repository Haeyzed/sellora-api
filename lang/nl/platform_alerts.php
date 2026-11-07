<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Wisselkoersen al :hours uur niet vernieuwd',
        'message' => 'Al minstens :hours uur is het ophalen van wisselkoersen niet gelukt. Winkels bieden hun extra valuta niet meer aan zodra de koersen :stale_hours uur oud zijn. Controleer de leverancier van de wisselkoersen.',
    ],
    'store_setup_failed' => [
        'title' => 'Het inrichten van winkel :store is mislukt',
        'message' => 'Het inrichten van winkel :store (:store_id) is na alle pogingen mislukt. Probeer het opnieuw vanuit de winkellijst zodra de oorzaak is verholpen.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Voorraadniveaus in :store kloppen niet met hun register',
        'message' => 'De geplande controle vond voorraadniveaus in winkel :store (:store_id) die niet kloppen met het register van voorraadbewegingen (:count). De details staan in het logboek van de controle.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Facturen in :store kloppen niet met de behouden verkopen',
        'message' => 'De geplande controle vond facturen in winkel :store (:store_id) waarvan de creditnota\'s niet kloppen met het behouden verkoopgeld of de gefactureerde belasting (:count). De details staan in het logboek van de controle.',
    ],
    'see_dashboard' => 'Meld je aan bij het platformdashboard van :app voor meer.',
];
