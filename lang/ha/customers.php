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
    'social_link' => [
        'subject' => 'Wani yana haɗa hanyar shiga da asusunka',
        'reason' => 'Wani ya shiga ta :provider ta wannan adireshin imel kuma ya nemi ya haɗa shi da asusunka. Ka tabbatar kawai idan kai ne: bayan haɗawa, shiga ta :provider zai buɗe asusunka.',
        'action' => 'Eh, haɗa :provider',
        'expiry' => 'Wannan hanyar tana aiki sau ɗaya kuma tana ƙarewa cikin :minutes minti.',
        'ignore' => 'Idan ba kai ba ne, ka yi watsi da wannan imel: babu abin da aka haɗa, kuma asusunka yana tsaye kamar yadda yake.',
    ],
    'erasure' => [
        'subject' => 'Ka tabbatar da share asusunka',
        'reason' => 'Ka nemi a share asusunka a wannan kantin. Buɗe wannan hanyar yayin da ka shiga don tabbatar: duk abin da kantin ke riƙe da kai za a share shi, kuma wannan ba za a iya canza shi ba.',
        'action' => 'Share asusuna',
        'expiry' => 'Wannan hanyar tana aiki sau ɗaya kuma tana ƙarewa cikin :minutes minti.',
        'ignore' => 'Idan ba ka nemi wannan ba, ka yi watsi da wannan imel: babu abin da aka share. Wani mutum na iya shiga asusunka, don haka ka fita daga sauran na’urorin ka.',
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
