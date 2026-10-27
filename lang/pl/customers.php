<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Potwierdź swój adres e-mail',
        'reason' => 'Potwierdź, że to jest Twój adres e-mail, abyś mógł zobaczyć swoje poprzednie zamówienia i pobrać swoje dane.',
        'action' => 'Potwierdź mój e-mail',
        'expiry' => 'Ten link działa jednorazowo i wygasa za :hours godzin.',
        'ignore' => 'Jeśli nie utworzyłeś konta, możesz zignorować tę wiadomość e-mail.',
    ],
    'email_change' => [
        'subject' => 'Potwierdź swój nowy adres e-mail',
        'reason' => 'Prosiłeś o użycie tego adresu dla swojego konta. Otwórz ten link po zalogowaniu, aby wprowadzić zmianę.',
        'action' => 'Skorzystaj z tego e-maila',
        'expiry' => 'Ten link działa jednorazowo i wygasa za :minutes minut.',
        'ignore' => 'Jeśli nie prosiłeś o to, możesz zignorować tego e-maila; nic się nie zmienia.',
    ],
    'social_link' => [
        'subject' => 'Ktoś łączy logowanie z Twoim kontem',
        'reason' => 'Ktoś zalogował się przez :provider, używając tego adresu e-mail, i poprosił o połączenie go z Twoim kontem. Potwierdź tylko wtedy, gdy to byłeś Ty: po połączeniu logowanie przez :provider otworzy Twoje konto.',
        'action' => 'Tak, połącz :provider',
        'expiry' => 'Ten link działa jednorazowo i wygasa za :minutes minut.',
        'ignore' => 'Jeśli to nie byłeś Ty, zignoruj tę wiadomość e-mail: nic nie zostanie połączone, a Twoje konto pozostanie bez zmian.',
    ],
    'erasure' => [
        'subject' => 'Potwierdź usunięcie konta',
        'reason' => 'Poprosiłeś o usunięcie konta w tym sklepie. Otwórz ten link po zalogowaniu, aby potwierdzić: wszystko, co sklep przechowuje o Tobie, zostanie usunięte i nie da się tego cofnąć.',
        'action' => 'Usuń moje konto',
        'expiry' => 'Ten link działa jednorazowo i wygasa za :minutes minut.',
        'ignore' => 'Jeśli nie prosiłeś o to, zignoruj tę wiadomość e-mail: nic nie zostanie usunięte. Ktoś inny może być zalogowany na Twoim koncie, dlatego wyloguj się z innych urządzeń.',
    ],
    'marketing_consent' => [
        'email' => 'Wysyłaj mi wiadomości i oferty od :store e-mailem. W każdej chwili mogę się wypisać.',
        'sms' => 'Wysyłaj mi wiadomości i oferty od :store SMS-em. W każdej chwili mogę się wypisać.',
    ],
    'email_changed' => [
        'subject' => 'Adres e-mail Twojego konta został zmieniony',
        'reason' => 'Adres e-mail Twojego konta został zmieniony, a wszystkie inne urządzenia zostały wylogowane. E-maile dotyczące Twojego konta będą teraz przesyłane na nowy adres.',
        'help' => 'Jeżeli to nie Ty dokonałeś tej zmiany, skontaktuj się od razu ze sklepem.',
    ],
];
