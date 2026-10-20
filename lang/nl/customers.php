<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Bevestig uw e-mailadres',
        'reason' => 'Bevestig dat dit uw e-mailadres is, zodat u uw eerdere bestellingen kunt zien en uw gegevens kunt downloaden.',
        'action' => 'Bevestig mijn e-mail',
        'expiry' => 'Deze link werkt eenmalig en verloopt over :hours uur.',
        'ignore' => 'Als u geen account heeft aangemaakt, kunt u deze e-mail negeren.',
    ],
    'email_change' => [
        'subject' => 'Bevestig uw nieuwe e-mailadres',
        'reason' => 'U heeft gevraagd dit adres te gebruiken voor uw account. Open deze link terwijl u bent aangemeld om de wijziging door te voeren.',
        'action' => 'Gebruik deze e-mail',
        'expiry' => 'Deze link werkt één keer en verloopt over :minutes minuten.',
        'ignore' => 'Als u hier niet om heeft gevraagd, kunt u deze e-mail negeren; er verandert niets.',
    ],
    'marketing_consent' => [
        'email' => 'Stuur mij nieuws en aanbiedingen van :store per e-mail. Ik kan me op elk moment afmelden.',
        'sms' => 'Stuur mij nieuws en aanbiedingen van :store per sms. Ik kan me op elk moment afmelden.',
    ],
    'email_changed' => [
        'subject' => 'Het e-mailadres van uw account is gewijzigd',
        'reason' => 'Het e-mailadres van uw account is gewijzigd en elk ander apparaat is uitgelogd. E-mails over uw account gaan nu naar het nieuwe adres.',
        'help' => 'Als u deze wijziging niet heeft aangebracht, neem dan onmiddellijk contact op met de winkel.',
    ],
];
