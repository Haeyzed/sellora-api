<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Thibitisha anwani yako ya barua pepe',
        'reason' => 'Thibitisha kuwa hii ni anwani yako ya barua pepe, ili uweze kuona maagizo yako ya awali na kupakua data yako.',
        'action' => 'Thibitisha barua pepe yangu',
        'expiry' => 'Kiungo hiki hufanya kazi mara moja na muda wake utaisha baada ya saa :hours.',
        'ignore' => 'Ikiwa hukufungua akaunti, unaweza kupuuza barua pepe hii.',
    ],
    'email_change' => [
        'subject' => 'Thibitisha anwani yako mpya ya barua pepe',
        'reason' => 'Uliomba kutumia anwani hii kwa akaunti yako. Fungua kiungo hiki ukiwa umeingia ili kufanya mabadiliko.',
        'action' => 'Tumia barua pepe hii',
        'expiry' => 'Kiungo hiki hufanya kazi mara moja na muda wake utaisha baada ya dakika :minutes.',
        'ignore' => 'Ikiwa hukuuliza hili, unaweza kupuuza barua pepe hii; hakuna mabadiliko.',
    ],
    'social_link' => [
        'subject' => 'Mtu anafungua njia ya kuingia kwenye akaunti yako',
        'reason' => 'Mtu aliingia kutumia :provider kwa anwani hii ya barua pepe na aliomba kuunganisha kwenye akaunti yako. Thibitisha tu ikiwa ulikuwa wewe: baada ya kuunganishwa, kuingia kwa :provider kutafungua akaunti yako.',
        'action' => 'Ndiyo, unganisha :provider',
        'expiry' => 'Kiungo hiki kinafanya kazi mara moja na kinaisha ndani ya dakika :minutes.',
        'ignore' => 'Kama si wewe, puzilia barua pepe hii: hakuna kilichounganishwa, na akaunti yako inabaki vile ilivyo.',
    ],
    'erasure' => [
        'subject' => 'Thibitisha kufuta akaunti yako',
        'reason' => 'Uliomba kufuta akaunti yako kwenye duka hili. Fungua kiungo hiki ukiwa umeingia katika akaunti yako ili kuthibitisha: kila kitu ambacho duka hili kina uhakika kukuhusu kitafutwa, na hii haiwezi kutenduliwa.',
        'action' => 'Futa akaunti yangu',
        'expiry' => 'Kiungo hiki kinafanya kazi mara moja na kinaisha ndani ya dakika :minutes.',
        'ignore' => 'Kama hukukuomba hili, puzilia barua pepe hii: hakuna kilichofutwa. Mtu mwingine anaweza kuwa ameingia kwenye akaunti yako, hivyo toka kwenye vifaa vyako vingine.',
    ],
    'marketing_consent' => [
        'email' => 'Nitumie habari na matoleo kutoka :store kwa barua pepe. Ninaweza kujiondoa wakati wowote.',
        'sms' => 'Nitumie habari na matoleo kutoka :store kwa SMS. Ninaweza kujiondoa wakati wowote.',
    ],
    'email_changed' => [
        'subject' => 'Anwani ya barua pepe ya akaunti yako ilibadilishwa',
        'reason' => 'Anwani ya barua pepe ya akaunti yako ilibadilishwa, na kila kifaa kingine kiliondolewa kwenye akaunti. Barua pepe kuhusu akaunti yako sasa zinaenda kwa anwani mpya.',
        'help' => 'Ikiwa hukufanya mabadiliko haya, wasiliana na duka mara moja.',
    ],
];
