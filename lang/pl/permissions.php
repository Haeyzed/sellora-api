<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Zarządzanie dokumentami prawnymi',
            'description' => 'Twórz, edytuj i publikuj dokumenty prawne akceptowane przez sprzedawców, takie jak warunki korzystania z usług.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Przeglądanie sklepów',
            'description' => 'Zobacz każdy sklep, jego status, plan, przyznane funkcje i przekroczenia limitów.',
        ],
        'manage' => [
            'label' => 'Zarządzanie sklepami',
            'description' => 'Zawieszaj, reaktywuj, zamykaj i przywracaj sklepy oraz spróbuj ponownie skonfigurować sklep, którego konfiguracja nie powiodła się.',
        ],
        'grant' => [
            'label' => 'Przyznawanie funkcji i limitów',
            'description' => 'Udostępnij sklepom moduły i integracje poza ich planem oraz zmień ich limity wykorzystania.',
        ],
        'export' => [
            'label' => 'Eksport danych sklepów',
            'description' => 'Eksportuj wszystkie dane sklepu, łącznie z danymi osobowymi każdego klienta.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Przeglądanie zespołu',
            'description' => 'Zobacz zespół: członkowie personelu, ich role i oczekujące zaproszenia.',
        ],
        'invite' => [
            'label' => 'Zapraszanie pracowników',
            'description' => 'Zapraszaj osoby do dołączenia do zespołu i ponownie wysyłaj lub anuluj zaproszenia.',
        ],
        'manage' => [
            'label' => 'Zarządzanie pracownikami',
            'description' => 'Zmień role członków personelu i dezaktywuj je lub reaktywuj.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Przeglądanie katalogu',
            'description' => 'Zobacz katalog: marki, kategorie i produkty, w tym wersje robocze, elementy zarchiwizowane i kosz.',
        ],
        'manage' => [
            'label' => 'Zarządzanie katalogiem',
            'description' => 'Twórz i zmieniaj marki, kategorie i produkty, przenoś je do kosza i przywracaj.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Przeglądanie stanów magazynowych',
            'description' => 'Zobacz poziomy zapasów, lokalizacje i historię każdej zmiany zapasów.',
        ],
        'adjust' => [
            'label' => 'Korekta stanów magazynowych',
            'description' => 'Otrzymuj zapasy, poprawiaj stany magazynowe i zmieniaj sposób obsługi zapasów każdego wariantu.',
        ],
        'costs' => [
            'label' => 'Koszty jednostkowe',
            'description' => 'Zobacz i zmień koszt każdej jednostki w sklepie. Koszty mają charakter wewnętrzny i nigdy nie są pokazywane klientom.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Przeglądanie klientów',
            'description' => 'Zobacz klientów, ich profile, adresy, notatki personelu i grupy, w tym konta dezaktywowane i usunięte.',
        ],
        'manage' => [
            'label' => 'Zarządzanie klientami',
            'description' => 'Zmieniaj profile klientów, dezaktywuj, reaktywuj, usuwaj i przywracaj konta, dodawaj lub usuwaj notatki oraz zarządzaj grupami klientów.',
        ],
        'tax_exemption' => [
            'label' => 'Zwolnienia podatkowe',
            'description' => 'Zapewnij klientowi zwolnienie z podatku lub zakończ je po sprawdzeniu jego identyfikatora podatkowego.',
        ],
        'privacy' => [
            'label' => 'Wnioski klientów o dane',
            'description' => 'Pobierz wszystkie dane osobowe klienta lub usuń je na stałe, na żądanie klienta.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Przeglądanie promocji',
            'description' => 'Zobacz promocje, związane z nimi kody i częstotliwość ich wykorzystania oraz zobacz, co zyskasz dzięki koszykowi.',
        ],
        'manage' => [
            'label' => 'Zarządzanie promocjami',
            'description' => 'Twórz, zmieniaj, aktywuj, dezaktywuj i archiwizuj promocje oraz dodawaj, generuj lub zmieniaj ich kody.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Przeglądanie wysyłki',
            'description' => 'Zobacz strefy wysyłki, metody i ich stawki oraz podgląd, ile koszyk zapłaci za wysyłkę.',
        ],
        'manage' => [
            'label' => 'Zarządzanie wysyłką',
            'description' => 'Twórz, zmieniaj i usuwaj strefy wysyłki oraz twórz, zmieniaj i archiwizuj metody wysyłki i ich stawki.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Przeglądanie zamówień',
            'description' => 'Zobacz zamówienia, ich linie, płatności, historię i przesyłki.',
        ],
        'manage' => [
            'label' => 'Zarządzanie zamówieniami',
            'description' => 'Anuluj zamówienia, dodawaj notatki i znaczniki oraz poprawiaj dane kontaktowe kupujących i adresy wysyłki.',
        ],
        'fulfil' => [
            'label' => 'Realizacja zamówień',
            'description' => 'Twórz przesyłki, dodawaj śledzenie i zaznaczaj, co zostało, jest gotowe do odbioru lub dotarło.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Przeglądanie faktur',
            'description' => 'Zobacz faktury i noty kredytowe z danymi kupującego oraz pobierz ich pliki PDF.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Przeglądanie płatności',
            'description' => 'Zobacz płatności, zwroty pieniędzy i sposób podłączenia bramek płatniczych.',
        ],
        'confirm' => [
            'label' => 'Potwierdzanie płatności',
            'description' => 'Potwierdź otrzymanie przelewu bankowego lub pobranie gotówki i ponownie zapytaj bramkę o płatność.',
        ],
        'refund' => [
            'label' => 'Zwroty płatności',
            'description' => 'Zwrot płatności za pośrednictwem bramki lub w formie zarejestrowanej ręcznie.',
        ],
        'manage_gateways' => [
            'label' => 'Zarządzanie bramkami płatności',
            'description' => 'Podłącz, odłącz i skonfiguruj bramki płatnicze i metody płatności.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Przeglądanie podatków i walut',
            'description' => 'Zobacz klasy podatkowe, stawki podatkowe, w jakiej klasie znajduje się każdy wariant oraz waluty i kursy wymiany obowiązujące w sklepie.',
        ],
        'manage' => [
            'label' => 'Zarządzanie podatkami i walutami',
            'description' => 'Zmień klasy podatkowe, stawki podatkowe, klasy podatkowe wariantów, waluty sklepu, kursy walut i ceny stałe.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Przeglądanie ustawień',
            'description' => 'Zobacz ustawienia sklepu: nazwę, kraj, walutę, języki, tryb podatkowy, jednostki i dane kontaktowe.',
        ],
        'manage' => [
            'label' => 'Zarządzanie ustawieniami',
            'description' => 'Zmień ustawienia sklepu. Wymaganie uwierzytelniania dwuskładnikowego w przypadku pobytu personelu u właściciela.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Przeglądanie ról',
            'description' => 'Zobacz role sklepu i możliwości każdej z nich.',
        ],
        'manage' => [
            'label' => 'Zarządzanie rolami',
            'description' => 'Twórz, zmieniaj nazwy i usuwaj role oraz wybieraj, na co pozwala każda z nich.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Przeglądanie dostaw',
            'description' => 'Przeglądanie kierowców i dostaw sklepu.',
        ],
        'manage' => [
            'label' => 'Zarządzanie dostawami',
            'description' => 'Przydzielać przesyłki kierowcom oraz przesuwać, ponawiać, odbierać z powrotem lub anulować ich dostawy.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Rejestrowanie gotówki kierowców',
                'description' => 'Rejestrować gotówkę przekazaną przez kierowców, nigdy więcej, niż mają.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Zarządzanie kierowcami',
            'description' => 'Dodawanie, zmienianie, dezaktywowanie i usuwanie kierowców oraz dawanie im kodów do wyboru PIN-u.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Przeglądanie zwrotów',
            'description' => 'Przeglądanie zwrotów, ich zdjęć i historii oraz ustawień zwrotów.',
        ],
        'manage' => [
            'label' => 'Zarządzanie zwrotami',
            'description' => 'Tworzenie, zatwierdzanie, odrzucanie, przyjmowanie, sprawdzanie i anulowanie zwrotów oraz zmiana ustawień zwrotów.',
        ],
    ],
];
