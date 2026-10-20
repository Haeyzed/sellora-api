<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Tabbatar da adireshin imel ɗin ku',
        'reason' => 'Tabbatar da cewa wannan shine adireshin imel ɗin ku, don haka za ku iya ganin odar ku ta baya da zazzage bayananku.',
        'action' => 'Tabbatar da imel na',
        'expiry' => 'Wannan hanyar haɗin yanar gizon tana aiki sau ɗaya kuma yana ƙarewa a cikin :hours hours.',
        'ignore' => 'Idan ba ku ƙirƙiri asusu ba, kuna iya yin watsi da wannan imel ɗin.',
    ],
    'email_change' => [
        'subject' => 'Tabbatar da sabon adireshin imel ɗin ku',
        'reason' => 'Kun nemi amfani da wannan adireshin don asusun ku. Bude wannan hanyar haɗin gwiwa yayin shiga don yin canji.',
        'action' => 'Yi amfani da wannan imel',
        'expiry' => 'Wannan hanyar haɗin yana aiki sau ɗaya kuma yana ƙarewa a cikin mintuna :minutes.',
        'ignore' => 'Idan ba ku nemi wannan ba, kuna iya watsi da wannan imel; babu abin da ke canzawa.',
    ],
    'marketing_consent' => [
        'email' => 'Aiko min labarai da tayi daga :store ta imel. Zan iya cire rajista a kowane lokaci.',
        'sms' => 'Aiko min labarai da tayi daga :store ta saƙon rubutu. Zan iya cire rajista a kowane lokaci.',
    ],
    'email_changed' => [
        'subject' => 'An canza adireshin imel na asusun ku',
        'reason' => 'An canza adireshin imel na asusun ku, kuma an fitar da kowace na\'ura. Saƙonnin imel game da asusunku yanzu suna zuwa sabon adireshin.',
        'help' => 'Idan baku yi wannan canjin ba, tuntuɓi kantin nan take.',
    ],
];
