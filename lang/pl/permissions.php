<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Twórz, edytuj i publikuj dokumenty prawne akceptowane przez sprzedawców, takie jak warunki korzystania z usług.',
    ],
    'stores' => [
        'view' => 'Zobacz każdy sklep, jego status, plan, przyznane funkcje i przekroczenia limitów.',
        'manage' => 'Zawieszaj, reaktywuj, zamykaj i przywracaj sklepy oraz spróbuj ponownie skonfigurować sklep, którego konfiguracja nie powiodła się.',
        'grant' => 'Udostępnij sklepom moduły i integracje poza ich planem oraz zmień ich limity wykorzystania.',
        'export' => 'Eksportuj wszystkie dane sklepu, łącznie z danymi osobowymi każdego klienta.',
    ],
    'staff' => [
        'view' => 'Zobacz zespół: członkowie personelu, ich role i oczekujące zaproszenia.',
        'invite' => 'Zapraszaj osoby do dołączenia do zespołu i ponownie wysyłaj lub anuluj zaproszenia.',
        'manage' => 'Zmień role członków personelu i dezaktywuj je lub reaktywuj.',
    ],
    'catalog' => [
        'view' => 'Zobacz katalog: marki, kategorie i produkty, w tym wersje robocze, elementy zarchiwizowane i kosz.',
        'manage' => 'Twórz i zmieniaj marki, kategorie i produkty, przenoś je do kosza i przywracaj.',
    ],
    'inventory' => [
        'view' => 'Zobacz poziomy zapasów, lokalizacje i historię każdej zmiany zapasów.',
        'adjust' => 'Otrzymuj zapasy, poprawiaj stany magazynowe i zmieniaj sposób obsługi zapasów każdego wariantu.',
        'costs' => 'Zobacz i zmień koszt każdej jednostki w sklepie. Koszty mają charakter wewnętrzny i nigdy nie są pokazywane klientom.',
    ],
    'customers' => [
        'view' => 'Zobacz klientów, ich profile, adresy, notatki personelu i grupy, w tym konta dezaktywowane i usunięte.',
        'manage' => 'Zmieniaj profile klientów, dezaktywuj, reaktywuj, usuwaj i przywracaj konta, dodawaj lub usuwaj notatki oraz zarządzaj grupami klientów.',
        'tax_exemption' => 'Zapewnij klientowi zwolnienie z podatku lub zakończ je po sprawdzeniu jego identyfikatora podatkowego.',
        'privacy' => 'Pobierz wszystkie dane osobowe klienta lub usuń je na stałe, na żądanie klienta.',
    ],
    'promotions' => [
        'view' => 'Zobacz promocje, związane z nimi kody i częstotliwość ich wykorzystania oraz zobacz, co zyskasz dzięki koszykowi.',
        'manage' => 'Twórz, zmieniaj, aktywuj, dezaktywuj i archiwizuj promocje oraz dodawaj, generuj lub zmieniaj ich kody.',
    ],
    'shipping' => [
        'view' => 'Zobacz strefy wysyłki, metody i ich stawki oraz podgląd, ile koszyk zapłaci za wysyłkę.',
        'manage' => 'Twórz, zmieniaj i usuwaj strefy wysyłki oraz twórz, zmieniaj i archiwizuj metody wysyłki i ich stawki.',
    ],
    'orders' => [
        'view' => 'Zobacz zamówienia, ich linie, płatności, historię i przesyłki.',
        'manage' => 'Anuluj zamówienia, dodawaj notatki i znaczniki oraz poprawiaj dane kontaktowe kupujących i adresy wysyłki.',
        'fulfil' => 'Twórz przesyłki, dodawaj śledzenie i zaznaczaj, co zostało, jest gotowe do odbioru lub dotarło.',
    ],
    'invoices' => [
        'view' => 'Zobacz faktury i noty kredytowe z danymi kupującego oraz pobierz ich pliki PDF.',
    ],
    'payments' => [
        'view' => 'Zobacz płatności, zwroty pieniędzy i sposób podłączenia bramek płatniczych.',
        'confirm' => 'Potwierdź otrzymanie przelewu bankowego lub pobranie gotówki i ponownie zapytaj bramkę o płatność.',
        'refund' => 'Zwrot płatności za pośrednictwem bramki lub w formie zarejestrowanej ręcznie.',
        'manage_gateways' => 'Podłącz, odłącz i skonfiguruj bramki płatnicze i metody płatności.',
    ],
    'pricing' => [
        'view' => 'Zobacz klasy podatkowe, stawki podatkowe, w jakiej klasie znajduje się każdy wariant oraz waluty i kursy wymiany obowiązujące w sklepie.',
        'manage' => 'Zmień klasy podatkowe, stawki podatkowe, klasy podatkowe wariantów, waluty sklepu, kursy walut i ceny stałe.',
    ],
    'settings' => [
        'view' => 'Zobacz ustawienia sklepu: nazwę, kraj, walutę, języki, tryb podatkowy, jednostki i dane kontaktowe.',
        'manage' => 'Zmień ustawienia sklepu. Wymaganie uwierzytelniania dwuskładnikowego w przypadku pobytu personelu u właściciela.',
    ],
    'roles' => [
        'view' => 'Zobacz role sklepu i możliwości każdej z nich.',
        'manage' => 'Twórz, zmieniaj nazwy i usuwaj role oraz wybieraj, na co pozwala każda z nich.',
    ],
    'delivery' => [
        'view' => 'Przeglądanie kierowców i dostaw sklepu.',
        'manage' => 'Przydzielać przesyłki kierowcom oraz przesuwać, ponawiać, odbierać z powrotem lub anulować ich dostawy.',
        'cash' => [
            'manage' => 'Rejestrować gotówkę przekazaną przez kierowców, nigdy więcej, niż mają.',
        ],
    ],
    'drivers' => [
        'manage' => 'Dodawanie, zmienianie, dezaktywowanie i usuwanie kierowców oraz dawanie im kodów do wyboru PIN-u.',
    ],
];
