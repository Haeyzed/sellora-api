<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner chce uczynić Cię właścicielem swojego sklepu',
        'reason' => ':owner poprosił Cię o przejęcie roli właściciela sklepu. Jako właściciel możesz zrobić wszystko w sklepie, a umowa sklepu z :app staje się Twoja.',
        'terms' => 'Aby zaakceptować, zaloguj się do panelu sklepu i zaakceptuj aktualne warunki korzystania z usługi :app. Nic się nie zmieni, dopóki tego nie zrobisz.',
        'action' => 'Przejrzyj transfer',
        'expiry' => 'Ta oferta wygasa za :hours godzin.',
        'ignore' => 'Jeśli nie chcesz przejmować sklepu, zignoruj ​​tę wiadomość.',
    ],
    'completed' => [
        'subject' => 'Sklep :store ma nowego właściciela',
        'new_owner' => 'Jesteś teraz właścicielem sklepu :store.',
        'previous_owner' => ':owner jest teraz właścicielem sklepu :store. Zachowujesz tylko role wybrane podczas rozpoczynania przenoszenia.',
        'not_you' => 'Jeśli się tego nie spodziewałeś, natychmiast skontaktuj się z obsługą :app.',
    ],
];
