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
