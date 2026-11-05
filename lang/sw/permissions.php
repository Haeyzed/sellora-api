<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Simamia nyaraka za kisheria',
            'description' => 'Rasimu, hariri na uchapishe hati za kisheria ambazo wafanyabiashara wanakubali, kama vile sheria na masharti.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Tazama maduka',
            'description' => 'Angalia kila duka, hali yake, mpango, ruzuku za vipengele na ubatilishaji wa kikomo.',
        ],
        'manage' => [
            'label' => 'Simamia maduka',
            'description' => 'Sitisha, anzisha tena, funga na urejeshe duka, na ujaribu tena kusanidi duka ambalo usanidi wake umeshindwa.',
        ],
        'grant' => [
            'label' => 'Toa vipengele na vikomo',
            'description' => 'Ipe maduka moduli na miunganisho nje ya mpango wao, na ubadilishe vikomo vyao vya matumizi.',
        ],
        'export' => [
            'label' => 'Hamisha data ya maduka',
            'description' => 'Hamisha data yote ya duka, ikijumuisha data ya kibinafsi ya kila mteja.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Tazama timu',
            'description' => 'Tazama timu: wafanyikazi, majukumu yao na mialiko inayosubiri.',
        ],
        'invite' => [
            'label' => 'Alika wafanyakazi',
            'description' => 'Alika watu wajiunge na timu, na utume tena au ughairi mialiko.',
        ],
        'manage' => [
            'label' => 'Simamia wafanyakazi',
            'description' => 'Badilisha majukumu ya wafanyikazi, na uwashe au uwashe tena.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Tazama katalogi',
            'description' => 'Tazama katalogi: chapa, kategoria na bidhaa, pamoja na rasimu, vitu vilivyohifadhiwa kwenye kumbukumbu na takataka.',
        ],
        'manage' => [
            'label' => 'Simamia katalogi',
            'description' => 'Unda na ubadilishe chapa, kategoria na bidhaa, zihamishe hadi kwenye tupio na uzirejeshe.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Tazama hisa',
            'description' => 'Angalia viwango vya hisa, maeneo na historia ya kila mabadiliko ya hisa.',
        ],
        'adjust' => [
            'label' => 'Rekebisha hisa',
            'description' => 'Pokea hisa, hesabu sahihi za hisa, na ubadilishe jinsi hisa ya kila kibadala inavyoshughulikiwa.',
        ],
        'costs' => [
            'label' => 'Gharama kwa kila kipande',
            'description' => 'Tazama na ubadilishe gharama ya kila kitengo kwenye duka. Gharama ni za ndani na hazionyeshwi kwa wateja.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Tazama wateja',
            'description' => 'Angalia wateja, wasifu wao, anwani, madokezo ya wafanyakazi na vikundi, ikijumuisha akaunti zilizozimwa na zilizotupwa.',
        ],
        'manage' => [
            'label' => 'Simamia wateja',
            'description' => 'Badilisha wasifu wa wateja, zima, wezesha tena, taka na urejeshe akaunti, ongeza au ufute madokezo na udhibiti vikundi vya wateja.',
        ],
        'tax_exemption' => [
            'label' => 'Misamaha ya kodi',
            'description' => 'Usitolewe kodi ya mteja, au uimalize, baada ya kuangalia kitambulisho chake cha kodi.',
        ],
        'privacy' => [
            'label' => 'Maombi ya data ya wateja',
            'description' => 'Pakua data yote ya kibinafsi ya mteja, au uifute kwa manufaa, kwa ombi la mteja.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Tazama ofa',
            'description' => 'Tazama matangazo, misimbo yao na mara ngapi yalitumiwa, na uhakiki kile kikapu kingepata.',
        ],
        'manage' => [
            'label' => 'Simamia ofa',
            'description' => 'Unda, badilisha, wezesha, zima na uhifadhi kwenye kumbukumbu ofa, na uongeze, utengeneze au ubadilishe misimbo yake.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Tazama usafirishaji',
            'description' => 'Tazama maeneo ya usafirishaji, mbinu na viwango vyake, na uhakikishe kile kikapu kingelipa kwa usafirishaji.',
        ],
        'manage' => [
            'label' => 'Simamia usafirishaji',
            'description' => 'Unda, badilisha na ufute maeneo ya usafirishaji, na uunde, ubadilishe na uhifadhi kwenye kumbukumbu mbinu za usafirishaji na viwango vyake.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Tazama oda',
            'description' => 'Angalia maagizo, laini zao, malipo, historia na usafirishaji.',
        ],
        'manage' => [
            'label' => 'Simamia oda',
            'description' => 'Ghairi maagizo, ongeza madokezo na lebo, na sahihisha maelezo ya mawasiliano ya wanunuzi na anwani za usafirishaji.',
        ],
        'fulfil' => [
            'label' => 'Tekeleza oda',
            'description' => 'Unda usafirishaji, ongeza ufuatiliaji, na utie alama kile kilichosalia, kiko tayari kuchukuliwa au kimefika.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Tazama ankara',
            'description' => 'Tazama ankara na noti za mkopo zilizo na maelezo ya mnunuzi, na upakue PDF zao.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Tazama malipo',
            'description' => 'Angalia malipo, urejeshaji fedha na jinsi lango la malipo linavyounganishwa.',
        ],
        'confirm' => [
            'label' => 'Thibitisha malipo',
            'description' => 'Thibitisha kuwa uhamishaji wa benki ulifika au pesa taslimu ilipowasilishwa zilikusanywa, na uulize lango tena kuhusu malipo.',
        ],
        'refund' => [
            'label' => 'Rejesha malipo',
            'description' => 'Malipo ya kurejesha pesa, kupitia lango au kama ilivyorekodiwa kwa mkono.',
        ],
        'manage_gateways' => [
            'label' => 'Simamia njia za malipo',
            'description' => 'Unganisha, tenganisha na uweke lango la malipo na njia za kulipa.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Tazama kodi na sarafu',
            'description' => 'Angalia madarasa ya kodi, viwango vya kodi, ambayo kila lahaja iko, na sarafu za duka na viwango vya ubadilishaji.',
        ],
        'manage' => [
            'label' => 'Simamia kodi na sarafu',
            'description' => 'Badilisha viwango vya kodi, viwango vya kodi, aina za kodi za vibadala, sarafu za duka, viwango vya kubadilisha fedha na bei zisizobadilika.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Tazama mipangilio',
            'description' => 'Angalia mipangilio ya duka: jina, nchi, sarafu, lugha, hali ya kodi, vitengo na maelezo ya mawasiliano.',
        ],
        'manage' => [
            'label' => 'Simamia mipangilio',
            'description' => 'Badilisha mipangilio ya duka. Inahitaji uthibitishaji wa sababu mbili kwa wafanyikazi hukaa na mmiliki.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Tazama majukumu',
            'description' => 'Tazama majukumu ya duka na kile ambacho kila moja inaruhusu.',
        ],
        'manage' => [
            'label' => 'Simamia majukumu',
            'description' => 'Unda, ubadilishe jina na ufute majukumu, na uchague kile ambacho kila kimoja kinaruhusu.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Tazama uwasilishaji',
            'description' => 'Ona madereva na usafirishaji wa duka.',
        ],
        'manage' => [
            'label' => 'Simamia uwasilishaji',
            'description' => 'Kuwapa madereva usafirishaji, na kusogeza, kurudia, kurejesha au kughairi uwasilishaji wao.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Rekodi pesa taslimu za madereva',
                'description' => 'Kurekodi pesa taslimu wanazokabidhi madereva, kamwe si zaidi ya walizonazo.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Simamia madereva',
            'description' => 'Ongeza, badilisha, zima na futa madereva, na uwape misimbo ya kuchagua PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Tazama marejesho',
            'description' => 'Kuona marejesho, picha na historia yake, na mipangilio ya marejesho.',
        ],
        'manage' => [
            'label' => 'Simamia marejesho',
            'description' => 'Kuanzisha, kuidhinisha, kukataa, kupokea, kukagua na kughairi marejesho, na kubadilisha mipangilio ya marejesho.',
        ],
        'exchange' => [
            'label' => 'Kuthibitisha ubadilishaji',
            'description' => 'Thibitisha ubadilishaji wa urudishaji: hii huunda agizo la kubadilisha na kutatua thamani ya bidhaa zilizorudishwa.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Kuona maudhui ya duka la mtandaoni',
            'description' => 'Kuona kurasa, menyu, mabango na mipangilio ya duka la mtandaoni.',
        ],
        'manage' => [
            'label' => 'Kusimamia maudhui ya duka la mtandaoni',
            'description' => 'Kuongeza, kubadilisha, kuchapisha na kuondoa kurasa, menyu na mabango ya duka, na kubadilisha mipangilio ya duka la mtandaoni.',
        ],
    ],
];
