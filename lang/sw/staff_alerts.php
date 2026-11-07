<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Agizo jipya :order',
        'message' => 'Agizo :order limewekwa kwa :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Malipo ya agizo :order yanahitaji kukaguliwa',
        'message' => 'Malipo ya :amount ya agizo :order hayakulingana na yaliyotarajiwa. Yakague kabla ya kushughulikia agizo.',
    ],
    'payment_unresolved' => [
        'title' => 'Malipo ya agizo :order hayajakamilika',
        'message' => 'Lango la malipo halijakamilisha malipo ya :amount ya agizo :order. Yakague kwa lango la malipo.',
    ],
    'payment_overpaid' => [
        'title' => 'Agizo :order limelipwa zaidi',
        'message' => 'Mnunuzi alilipa :amount zaidi ya bei ya agizo :order. Rudisha tofauti.',
    ],
    'payment_disputed' => [
        'title' => 'Malipo yamepingwa',
        'message' => 'Mnunuzi amepinga malipo yaliyofanywa kupitia :gateway. Jibu malalamiko kwenye dashibodi ya lango kabla ya muda wake kuisha.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Unganisha upya :gateway',
        'message' => ':gateway imekataa funguo za duka. Malipo kupitia hiyo yanasubiri hadi uiunganishe upya.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Agizo lililoghairiwa :order limelipwa',
        'message' => 'Malipo ya agizo :order yamefika baada ya kughairiwa. Agizo linabaki limeghairiwa; rudisha malipo.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Agizo :order limerejeshewa zaidi ya kilicholipwa',
        'message' => 'Marejesho ya agizo :order yanazidi kile mnunuzi alicholipa. Kagua marejesho ya agizo.',
    ],
    'refund_needs_review' => [
        'title' => 'Marejesho ya agizo :order yanahitaji kukaguliwa',
        'message' => 'Marejesho ya :amount ya agizo :order yanahitaji kukaguliwa: jibu la lango halikuwa wazi.',
    ],
    'refund_failed' => [
        'title' => 'Marejesho ya agizo :order yameshindwa',
        'message' => 'Marejesho ya :amount ya agizo :order yameshindwa. Mrejeshee mnunuzi kwa njia nyingine, au jaribu tena.',
    ],
    'refund_still_pending' => [
        'title' => 'Marejesho ya agizo :order bado yanasubiri',
        'message' => 'Marejesho ya :amount ya agizo :order yamekuwa yakisubiri kwenye lango kwa siku kadhaa. Yakague kwa lango.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Marejesho yaliyoshindwa ya agizo :order yamefaulu',
        'message' => 'Marejesho ya :amount ya agizo :order, yaliyoripotiwa kushindwa, yamefaulu hatimaye. Ikiwa ulirejesha tena, mnunuzi amerejeshewa mara mbili.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Malipo ya agizo :order hayawezi kukamilishwa',
        'message' => 'Kukamilisha malipo ya :method ya :amount ya agizo :order kunaendelea kushindwa. Yanajaribiwa tena kila saa.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Malipo ya agizo :order hayawezi kurekodiwa',
        'message' => 'Kurekodi malipo ya agizo :order kunaendelea kushindwa. Yanajaribiwa tena kila saa.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Pesa za agizo lililoghairiwa :order haziwezi kurudishwa',
        'message' => 'Malipo ya :method ya :amount ya agizo lililoghairiwa :order hayakuweza kurudishwa. Yanajaribiwa tena kila saa, na agizo bado linadaiwa marejesho.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Ubadilishaji wa agizo :order haukukamilishwa',
        'message' => 'Agizo mbadala :order limelipwa, lakini kukamilisha urejeshaji wake kumekataliwa (:reason). Likamilishe tena kutoka kwenye urejeshaji.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Pesa taslimu za agizo :order hazijarekodiwa',
        'message' => 'Agizo :order limefikishwa, lakini :amount taslimu hazijarekodiwa kama zimekusanywa.',
    ],
    'delivery_failed' => [
        'title' => 'Usafirishaji wa agizo :order umeshindwa',
        'message' => 'Dereva hakuweza kufikisha agizo :order. Panga usafirishaji mwingine au wasiliana na mnunuzi.',
    ],
    'stock_running_low' => [
        'title' => ':product inakaribia kuisha',
        'message' => 'Zimebaki :available tu za :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product imeisha',
        'message' => ':product (SKU :sku) imeisha.',
    ],
    'return_requested' => [
        'title' => 'Urejeshaji umeombwa kwa agizo :order',
        'message' => 'Mnunuzi ameomba kurejesha bidhaa kutoka agizo :order.',
    ],
    'waiting' => [
        'title' => 'Kuna arifa zaidi zinazosubiri',
        'message' => 'Saa hii zimefika arifa nyingi kuliko zinazotumwa kwa barua pepe. Zione zote kwenye dashibodi yako.',
    ],
    'see_dashboard' => 'Ione kwenye dashibodi ya duka lako.',
];
