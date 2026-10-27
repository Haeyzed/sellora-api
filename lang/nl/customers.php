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
    'social_link' => [
        'subject' => 'Iemand verbindt een inlogmethode met uw account',
        'reason' => 'Iemand heeft zich met :provider aangemeld met dit e-mailadres en heeft gevraagd om dit aan uw account te koppelen. Bevestig alleen als u het was: zodra het is gekoppeld, opent inloggen met :provider uw account.',
        'action' => 'Ja, koppel :provider',
        'expiry' => 'Deze link werkt één keer en verloopt na :minutes minuten.',
        'ignore' => 'Als u dit niet was, negeer deze e-mail: er is niets gekoppeld en uw account blijft zoals hij is.',
    ],
    'erasure' => [
        'subject' => 'Bevestig het wissen van uw account',
        'reason' => 'U heeft gevraagd om uw account in deze winkel te wissen. Open deze link terwijl u bent ingelogd om te bevestigen: alles wat de winkel over u bewaart, wordt gewist en dit kan niet ongedaan worden gemaakt.',
        'action' => 'Wis mijn account',
        'expiry' => 'Deze link werkt één keer en verloopt na :minutes minuten.',
        'ignore' => 'Als u dit niet heeft aangevraagd, negeer deze e-mail: er is niets gewist. Iemand anders kan zijn ingelogd op uw account, dus meld u af op uw andere apparaten.',
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
