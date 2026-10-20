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
