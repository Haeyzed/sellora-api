<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Kwenye adreesị email gị',
        'reason' => 'Kwenye na nke a bụ adreesị ozi-e gị, ka ị nwee ike ịhụ iwu gị gara aga wee budata data gị.',
        'action' => 'Kwenye email m',
        'expiry' => 'Njikọ a na-arụ ọrụ otu ugboro ma kubie ume n\'ime awa :hours.',
        'ignore' => 'Ọ bụrụ na ị mepụtaghị akaụntụ, ị nwere ike ileghara email a anya.',
    ],
    'email_change' => [
        'subject' => 'Kwenye adreesị ozi-e ọhụrụ gị',
        'reason' => 'Ị rịọrọ ka ị jiri adreesị a maka akaụntụ gị. Mepee njikọ a mgbe ị banyere ime mgbanwe.',
        'action' => 'Jiri email a',
        'expiry' => 'Njikọ a na-arụ ọrụ otu ugboro ma kubie ume na nkeji :minutes.',
        'ignore' => 'Ọ bụrụ na ị rịọghị maka nke a, ị nwere ike ileghara ozi-e a anya; ọ dịghị ihe na-agbanwe.',
    ],
    'social_link' => [
        'subject' => 'Nwoke/anyị na-ejikọta ụzọ ịbanye na akaụntụ gị',
        'reason' => 'Nwoke/anyị debanyere na :provider site adreesị email a wee rịọ ka ọ jikọta ya na akaụntụ gị. Kwenye ma ọ bụrụ gị: mgbe ejikọtara, ịbanye na :provider ga-eme ka akaụntụ gị mepee.',
        'action' => 'Ee, jikọta :provider',
        'expiry' => 'Njikọ a na-ejikari otu oge ma ọ gaghị agwụcha na :minutes nkeji.',
        'ignore' => 'Ọ bụrụ na ọ bụghị gị, lee email a anya: ọ dịghị ihe ejikọtara, akaụntụ gị ka dị ka ọ dị.',
    ],
    'erasure' => [
        'subject' => 'Kwenye ihichapụ akaụntụ gị',
        'reason' => 'I rịọla ka akpụchapụ akaụntụ gị na ụlọ ahịa a. Mepee njikọ a mgbe ịbanye n’akaụntụ iji kwe: ihe niile ụlọahịa nwere gbasara gị ga-ewepụ, ọ nweghị ike ịmegharị ya.',
        'action' => 'Hichapụ akaụntụ m',
        'expiry' => 'Njikọ a na-ejikari otu oge ma ọ gaghị agwụcha na :minutes nkeji.',
        'ignore' => 'Ọ bụrụ na ịghị rịọ nke a, lee email a anya: ọ dịghị ihe ewepụrụ. Onye ọzọ nwere ike ịbanye na akaụntụ gị, ya mere pụọ na ngwaọrụ ndị ọzọ gị.',
    ],
    'marketing_consent' => [
        'email' => 'Zitere m akụkọ na onyinye sitere na :store site na email. Enwere m ike ịwepu aha oge ọ bụla.',
        'sms' => 'Zitere m akụkọ na onyinye sitere na :store site na ozi ederede. Enwere m ike ịwepu aha oge ọ bụla.',
    ],
    'email_changed' => [
        'subject' => 'Agbanwere adreesị ozi-e akaụntụ gị',
        'reason' => 'Agbanwere adreesị ozi-e nke akaụntụ gị, ahapụkwara ngwaọrụ ọ bụla ọzọ. Ozi ịntanetị gbasara akaụntụ gị ugbu a na-aga na adreesị ọhụrụ.',
        'help' => 'Ọ bụrụ na i meghị mgbanwe a, kpọtụrụ ụlọ ahịa ozugbo.',
    ],
];
