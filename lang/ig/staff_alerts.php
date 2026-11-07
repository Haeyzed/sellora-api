<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Ọda ọhụrụ :order',
        'message' => 'E nyere ọda :order maka :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Ịkwụ ụgwọ maka ọda :order chọrọ nlele',
        'message' => 'Ịkwụ ụgwọ :amount maka ọda :order ekwekọghị n\'ihe a tụrụ anya ya. Lelee ya tupu ị zipụ ọda ahụ.',
    ],
    'payment_unresolved' => [
        'title' => 'Ịkwụ ụgwọ maka ọda :order agwụbeghị',
        'message' => 'Ọnụ ụzọ ịkwụ ụgwọ emechabeghị ịkwụ ụgwọ :amount maka ọda :order. Lelee ya n\'ọnụ ụzọ ahụ.',
    ],
    'payment_overpaid' => [
        'title' => 'A kwụfere ọda :order ụgwọ karịa',
        'message' => 'Onye zụrụ kwụrụ :amount karịa ọnụ ahịa ọda :order. Weghachi ego fọdụrụ.',
    ],
    'payment_disputed' => [
        'title' => 'E megidere ịkwụ ụgwọ',
        'message' => 'Onye zụrụ megidere ịkwụ ụgwọ e mere site na :gateway. Zaa mmegide ahụ na dashboard nke ọnụ ụzọ ahụ tupu oge ya agwụ.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Jikọọ :gateway ọzọ',
        'message' => ':gateway jụrụ igodo ụlọ ahịa. Ịkwụ ụgwọ site na ya ga-echere ruo mgbe ị jikọrọ ya ọzọ.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'A kwụrụ ụgwọ ọda :order a kagburu',
        'message' => 'Ịkwụ ụgwọ maka ọda :order bịara mgbe a kagburu ya. Ọda ahụ ka bụ nke a kagburu; weghachi ụgwọ ahụ.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'E weghachiri ọda :order ego karịa ihe a kwụrụ',
        'message' => 'Ego e weghachiri maka ọda :order karịrị ihe onye zụrụ kwụrụ. Lelee ego e weghachiri maka ọda ahụ.',
    ],
    'refund_needs_review' => [
        'title' => 'Iweghachi ego maka ọda :order chọrọ nlele',
        'message' => 'Iweghachi ego :amount maka ọda :order chọrọ nlele: azịza ọnụ ụzọ ahụ edoghị anya.',
    ],
    'refund_failed' => [
        'title' => 'Iweghachi ego maka ọda :order dara',
        'message' => 'Iweghachi ego :amount maka ọda :order dara. Weghachiere onye zụrụ ego n\'ụzọ ọzọ, ma ọ bụ nwaa ọzọ.',
    ],
    'refund_still_pending' => [
        'title' => 'Iweghachi ego maka ọda :order ka na-echere',
        'message' => 'Iweghachi ego :amount maka ọda :order anọọla ọtụtụ ụbọchị na-echere n\'ọnụ ụzọ. Lelee ya n\'ọnụ ụzọ ahụ.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Iweghachi ego dara maka ọda :order gara nke ọma',
        'message' => 'Iweghachi ego :amount maka ọda :order, nke a kọrọ na ọ dara, gara nke ọma n\'ikpeazụ. Ọ bụrụ na i weghachiri ọzọ, onye zụrụ nwetara ya ugboro abụọ.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Enweghị ike imechi ịkwụ ụgwọ maka ọda :order',
        'message' => 'Imechi ịkwụ ụgwọ :method nke :amount maka ọda :order na-adakarị. A na-anwa ya ọzọ kwa awa.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Enweghị ike idekọ ịkwụ ụgwọ ọda :order',
        'message' => 'Idekọ ịkwụ ụgwọ ọda :order na-adakarị. A na-anwa ya ọzọ kwa awa.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Enweghị ike iweghachi ego ọda :order a kagburu',
        'message' => 'Enweghị ike iweghachi ịkwụ ụgwọ :method nke :amount maka ọda :order a kagburu. A na-anwa ya ọzọ kwa awa, ọda ahụ ka ji ụgwọ iweghachi ego.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Emechabeghị mgbanwe maka ọda :order',
        'message' => 'A kwụrụ ụgwọ ọda nnọchi :order, mana a jụrụ imechi nlọghachi ya (:reason). Mechie ya ọzọ site na nlọghachi ahụ.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Edekọbeghị ego aka maka ọda :order',
        'message' => 'E zigara ọda :order, mana edekọbeghị :amount ego aka dị ka nke a natara.',
    ],
    'delivery_failed' => [
        'title' => 'Nzipu ọda :order dara',
        'message' => 'Onye ọkwọ ụgbọ enweghị ike izipu ọda :order. Hazie nzipu ọzọ ma ọ bụ kpọtụrụ onye zụrụ.',
    ],
    'stock_running_low' => [
        'title' => ':product na-agwụ',
        'message' => 'Naanị :available fọdụrụ nke :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product agwụla',
        'message' => ':product (SKU :sku) agwụla.',
    ],
    'return_requested' => [
        'title' => 'Arịọrọ nlọghachi maka ọda :order',
        'message' => 'Onye zụrụ rịọrọ ka o weghachi ngwaahịa site na ọda :order.',
    ],
    'waiting' => [
        'title' => 'Ọkwa ndị ọzọ na-echere',
        'message' => 'N\'awa a, ọkwa bịara karịa ndị a na-ezipu site na email. Hụ ha niile na dashboard gị.',
    ],
    'see_dashboard' => 'Hụ ya na dashboard ụlọ ahịa gị.',
];
