<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Ìbéèrè tuntun :order',
        'message' => 'A ṣe ìbéèrè ọjà :order fún :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Ìsanwó kan fún ìbéèrè :order nílò àyẹ̀wò',
        'message' => 'Ìsanwó :amount fún ìbéèrè :order kò bá ohun tí a retí mu. Yẹ̀ ẹ́ wò kí o tó fi ìbéèrè náà ránṣẹ́.',
    ],
    'payment_unresolved' => [
        'title' => 'Ìsanwó kan fún ìbéèrè :order kò tíì yanjú',
        'message' => 'Ẹnu ọ̀nà ìsanwó kò tíì parí ìsanwó :amount fún ìbéèrè :order. Yẹ̀ ẹ́ wò lọ́dọ̀ ẹnu ọ̀nà ìsanwó.',
    ],
    'payment_overpaid' => [
        'title' => 'A san ju iye ìbéèrè :order lọ',
        'message' => 'Olùrà san :amount ju iye ìbéèrè :order lọ. Dá ìyàtọ̀ náà padà.',
    ],
    'payment_disputed' => [
        'title' => 'Wọ́n ta ko ìsanwó kan',
        'message' => 'Olùrà kan ta ko ìsanwó tí a ṣe nípasẹ̀ :gateway. Fèsì sí àtakò náà ní dashboard ẹnu ọ̀nà ìsanwó kí àkókò rẹ̀ tó pé.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'So :gateway pọ̀ padà',
        'message' => ':gateway kọ àwọn kọ́kọ́rọ́ ilé ìtajà. Àwọn ìsanwó nípasẹ̀ rẹ̀ yóò dúró títí o fi so ó pọ̀ padà.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Wọ́n san owó ìbéèrè :order tí a fagilé',
        'message' => 'Ìsanwó kan dé fún ìbéèrè :order lẹ́yìn tí a fagilé e. Ìbéèrè náà ṣì wà ní fífagilé; dá owó náà padà.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'A dá owó padà fún ìbéèrè :order ju ohun tí a san lọ',
        'message' => 'Àpapọ̀ owó tí a dá padà fún ìbéèrè :order ju ohun tí olùrà san lọ. Yẹ àwọn owó tí a dá padà fún ìbéèrè náà wò.',
    ],
    'refund_needs_review' => [
        'title' => 'Owó tí a fẹ́ dá padà fún ìbéèrè :order nílò àyẹ̀wò',
        'message' => 'Owó :amount tí a fẹ́ dá padà fún ìbéèrè :order nílò àyẹ̀wò: ìdáhùn ẹnu ọ̀nà ìsanwó kò ṣe kedere.',
    ],
    'refund_failed' => [
        'title' => 'Dídá owó padà fún ìbéèrè :order kùnà',
        'message' => 'Dídá owó :amount padà fún ìbéèrè :order kùnà. Dá owó padà fún olùrà ní ọ̀nà mìíràn, tàbí gbìyànjú lẹ́ẹ̀kan sí i.',
    ],
    'refund_still_pending' => [
        'title' => 'Dídá owó padà fún ìbéèrè :order ṣì ń dúró',
        'message' => 'Dídá owó :amount padà fún ìbéèrè :order ti ń dúró lọ́dọ̀ ẹnu ọ̀nà ìsanwó fún ọjọ́ púpọ̀. Yẹ̀ ẹ́ wò lọ́dọ̀ ẹnu ọ̀nà ìsanwó.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Dídá owó padà tó kùnà fún ìbéèrè :order ti lọ',
        'message' => 'Dídá owó :amount padà fún ìbéèrè :order, tí a sọ pé ó kùnà, ti lọ níkẹyìn. Bí o bá tún dá owó padà, olùrà ti gba owó padà lẹ́ẹ̀mejì.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Kò ṣeé ṣe láti parí ìsanwó kan fún ìbéèrè :order',
        'message' => 'Píparí ìsanwó :method ti :amount fún ìbéèrè :order ń kùnà ṣáá. A ń gbìyànjú rẹ̀ lẹ́ẹ̀kan sí i ní gbogbo wákàtí.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Kò ṣeé ṣe láti kọ àwọn ìsanwó ìbéèrè :order sílẹ̀',
        'message' => 'Kíkọ àwọn ìsanwó ìbéèrè :order sílẹ̀ ń kùnà ṣáá. A ń gbìyànjú rẹ̀ lẹ́ẹ̀kan sí i ní gbogbo wákàtí.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Kò ṣeé ṣe láti dá owó ìbéèrè :order tí a fagilé padà',
        'message' => 'A kò lè dá ìsanwó :method ti :amount fún ìbéèrè :order tí a fagilé padà. A ń gbìyànjú rẹ̀ lẹ́ẹ̀kan sí i ní gbogbo wákàtí, ìbéèrè náà sì ṣì jẹ owó ìdápadà.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'A kò parí pàṣípààrọ̀ kan fún ìbéèrè :order',
        'message' => 'Wọ́n ti san ìbéèrè ìrọ́pò :order, ṣùgbọ́n wọ́n kọ píparí ìdápadà rẹ̀ (:reason). Parí rẹ̀ lẹ́ẹ̀kan sí i láti inú ìdápadà náà.',
    ],
    'cash_collection_overdue' => [
        'title' => 'A kò tíì kọ owó ọwọ́ ìbéèrè :order sílẹ̀',
        'message' => 'A ti fi ìbéèrè :order jíṣẹ́, ṣùgbọ́n a kò tíì kọ owó ọwọ́ :amount sílẹ̀ bí èyí tí a gbà.',
    ],
    'delivery_failed' => [
        'title' => 'Fífi ìbéèrè :order jíṣẹ́ kùnà',
        'message' => 'Awakọ̀ kò lè fi ìbéèrè :order jíṣẹ́. Ṣètò ìfijíṣẹ́ mìíràn tàbí kàn sí olùrà.',
    ],
    'stock_running_low' => [
        'title' => ':product ń tán lọ',
        'message' => ':available péré ló kù nínú :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product ti tán',
        'message' => ':product (SKU :sku) ti tán.',
    ],
    'return_requested' => [
        'title' => 'Wọ́n béèrè láti dá ọjà ìbéèrè :order padà',
        'message' => 'Olùrà kan béèrè láti dá àwọn ọjà láti inú ìbéèrè :order padà.',
    ],
    'waiting' => [
        'title' => 'Àwọn ìkìlọ̀ mìíràn ń dúró',
        'message' => 'Ní wákàtí yìí, àwọn ìkìlọ̀ tó dé pọ̀ ju èyí tí a fi ímeèlì ránṣẹ́ lọ. Wo gbogbo wọn ní dashboard rẹ.',
    ],
    'see_dashboard' => 'Wò ó ní dashboard ilé ìtajà rẹ.',
];
