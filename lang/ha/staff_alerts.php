<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Sabon oda :order',
        'message' => 'An yi oda :order akan :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Ana buƙatar duba wani biyan kuɗi na oda :order',
        'message' => 'Biyan kuɗi na :amount don oda :order bai yi daidai da abin da ake tsammani ba. Ka duba shi kafin ka aika oda.',
    ],
    'payment_unresolved' => [
        'title' => 'Wani biyan kuɗi na oda :order bai kammala ba',
        'message' => 'Ƙofar biyan kuɗi ba ta kammala biyan kuɗi na :amount don oda :order ba. Ka duba shi a ƙofar biyan kuɗi.',
    ],
    'payment_overpaid' => [
        'title' => 'An biya oda :order fiye da kima',
        'message' => 'Mai saye ya biya :amount fiye da kuɗin oda :order. Ka mayar da bambancin.',
    ],
    'payment_disputed' => [
        'title' => 'An ƙalubalanci wani biyan kuɗi',
        'message' => 'Wani mai saye ya ƙalubalanci biyan kuɗi da aka yi ta :gateway. Ka amsa ƙalubalen a dashboard ɗin ƙofar kafin wa\'adinsa ya cika.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Sake haɗa :gateway',
        'message' => ':gateway ya ƙi maɓallan shagon. Biyan kuɗi ta hanyarsa zai tsaya har sai ka sake haɗa shi.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'An biya oda :order da aka soke',
        'message' => 'Biyan kuɗi ya iso don oda :order bayan an soke ta. Oda ta kasance a soke; ka mayar da biyan kuɗin.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'An mayar wa oda :order fiye da abin da aka biya',
        'message' => 'Kuɗin da aka mayar don oda :order sun fi abin da mai saye ya biya. Ka duba kuɗin da aka mayar na odar.',
    ],
    'refund_needs_review' => [
        'title' => 'Ana buƙatar duba wani mayar da kuɗi na oda :order',
        'message' => 'Ana buƙatar duba mayar da kuɗi na :amount don oda :order: amsar ƙofar ba ta bayyana ba.',
    ],
    'refund_failed' => [
        'title' => 'Mayar da kuɗi na oda :order ya gaza',
        'message' => 'Mayar da kuɗi na :amount don oda :order ya gaza. Ka mayar wa mai saye ta wata hanya, ko ka sake gwadawa.',
    ],
    'refund_still_pending' => [
        'title' => 'Mayar da kuɗi na oda :order yana jira har yanzu',
        'message' => 'Mayar da kuɗi na :amount don oda :order ya kwashe kwanaki yana jira a ƙofar. Ka duba shi a ƙofar.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Mayar da kuɗi na oda :order da ya gaza ya wuce',
        'message' => 'Mayar da kuɗi na :amount don oda :order, wanda aka ce ya gaza, ya wuce daga ƙarshe. Idan ka sake mayarwa, an mayar wa mai saye sau biyu.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Ba za a iya kammala wani biyan kuɗi na oda :order ba',
        'message' => 'Kammala biyan kuɗi na :method na :amount don oda :order yana ci gaba da gazawa. Ana sake gwadawa kowace awa.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Ba za a iya rubuta biyan kuɗin oda :order ba',
        'message' => 'Rubuta biyan kuɗin oda :order yana ci gaba da gazawa. Ana sake gwadawa kowace awa.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Ba za a iya mayar da kuɗin oda :order da aka soke ba',
        'message' => 'Ba a iya mayar da biyan kuɗi na :method na :amount don oda :order da aka soke ba. Ana sake gwadawa kowace awa, kuma oda har yanzu tana bin mayar da kuɗi.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Ba a kammala musaya don oda :order ba',
        'message' => 'An biya oda ta maye :order, amma an ƙi kammala mayar da kayanta (:reason). Ka sake kammala shi daga mayar da kayan.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Ba a rubuta tsabar kuɗin oda :order ba',
        'message' => 'An kai oda :order, amma ba a rubuta :amount na tsabar kuɗi a matsayin an karɓa ba.',
    ],
    'delivery_failed' => [
        'title' => 'Kai oda :order ya gaza',
        'message' => 'Direba bai iya kai oda :order ba. Ka shirya wani kai kaya ko ka tuntuɓi mai saye.',
    ],
    'stock_running_low' => [
        'title' => ':product yana ƙarewa',
        'message' => ':available kawai ya rage na :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product ya ƙare',
        'message' => ':product (SKU :sku) ya ƙare.',
    ],
    'return_requested' => [
        'title' => 'An nemi mayar da kaya don oda :order',
        'message' => 'Wani mai saye ya nemi mayar da kaya daga oda :order.',
    ],
    'waiting' => [
        'title' => 'Akwai ƙarin sanarwa da ke jira',
        'message' => 'A wannan awa sanarwa sun zo fiye da waɗanda ake aika ta imel. Ka gan su duka a dashboard ɗinka.',
    ],
    'see_dashboard' => 'Ka gani a dashboard ɗin shagonka.',
];
