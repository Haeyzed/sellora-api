<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'नया ऑर्डर :order',
        'message' => 'ऑर्डर :order :amount का दिया गया।',
    ],
    'payment_needs_review' => [
        'title' => 'ऑर्डर :order का एक भुगतान जाँचना है',
        'message' => 'ऑर्डर :order के लिए :amount का एक भुगतान अपेक्षा से मेल नहीं खाया। ऑर्डर भेजने से पहले इसे जाँचें।',
    ],
    'payment_unresolved' => [
        'title' => 'ऑर्डर :order का एक भुगतान अनसुलझा है',
        'message' => 'गेटवे ने ऑर्डर :order के :amount के भुगतान को पूरा नहीं किया। गेटवे से इसकी जाँच करें।',
    ],
    'payment_overpaid' => [
        'title' => 'ऑर्डर :order का अधिक भुगतान हुआ',
        'message' => 'खरीदार ने ऑर्डर :order की कीमत से :amount अधिक चुकाए। अंतर की राशि लौटाएँ।',
    ],
    'payment_disputed' => [
        'title' => 'एक भुगतान पर विवाद हुआ',
        'message' => 'एक खरीदार ने :gateway से किए गए भुगतान पर विवाद किया। समय-सीमा से पहले गेटवे के डैशबोर्ड में विवाद का उत्तर दें।',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => ':gateway को फिर से जोड़ें',
        'message' => ':gateway ने स्टोर की कुंजियाँ अस्वीकार कर दीं। फिर से जोड़ने तक इसके द्वारा भुगतान लंबित रहेंगे।',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'रद्द ऑर्डर :order का भुगतान हुआ',
        'message' => 'ऑर्डर :order रद्द होने के बाद उसका भुगतान आया। ऑर्डर रद्द ही रहेगा; भुगतान लौटाएँ।',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'ऑर्डर :order में भुगतान से अधिक रिफ़ंड हुआ',
        'message' => 'ऑर्डर :order के रिफ़ंड खरीदार के भुगतान से अधिक हैं। ऑर्डर के रिफ़ंड जाँचें।',
    ],
    'refund_needs_review' => [
        'title' => 'ऑर्डर :order का एक रिफ़ंड जाँचना है',
        'message' => 'ऑर्डर :order के :amount के रिफ़ंड की जाँच ज़रूरी है: गेटवे का उत्तर स्पष्ट नहीं था।',
    ],
    'refund_failed' => [
        'title' => 'ऑर्डर :order का एक रिफ़ंड विफल रहा',
        'message' => 'ऑर्डर :order का :amount का रिफ़ंड विफल रहा। खरीदार को किसी और तरीके से रिफ़ंड दें या फिर से प्रयास करें।',
    ],
    'refund_still_pending' => [
        'title' => 'ऑर्डर :order का एक रिफ़ंड अब भी लंबित है',
        'message' => 'ऑर्डर :order का :amount का रिफ़ंड कई दिनों से गेटवे पर रुका है। गेटवे से इसकी जाँच करें।',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'ऑर्डर :order का विफल रिफ़ंड पूरा हो गया',
        'message' => 'ऑर्डर :order का :amount का रिफ़ंड, जो विफल बताया गया था, आख़िरकार पूरा हो गया। यदि आपने फिर से रिफ़ंड दिया, तो खरीदार को दो बार रिफ़ंड मिला।',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'ऑर्डर :order का एक भुगतान पूरा नहीं हो पा रहा',
        'message' => 'ऑर्डर :order के :amount के :method भुगतान को पूरा करना बार-बार विफल हो रहा है। हर घंटे फिर से प्रयास होता है।',
    ],
    'order_payments_keep_failing' => [
        'title' => 'ऑर्डर :order के भुगतान दर्ज नहीं हो पा रहे',
        'message' => 'ऑर्डर :order के भुगतान दर्ज करना बार-बार विफल हो रहा है। हर घंटे फिर से प्रयास होता है।',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'रद्द ऑर्डर :order का पैसा लौटाया नहीं जा पा रहा',
        'message' => 'रद्द ऑर्डर :order का :amount का :method भुगतान लौटाया नहीं जा सका। हर घंटे फिर से प्रयास होता है, और ऑर्डर पर रिफ़ंड अब भी बकाया है।',
    ],
    'exchange_settlement_refused' => [
        'title' => 'ऑर्डर :order का एक एक्सचेंज निपटाया नहीं गया',
        'message' => 'बदले का ऑर्डर :order चुका दिया गया, पर उसके रिटर्न का निपटान अस्वीकार हुआ (:reason)। रिटर्न से इसे फिर से निपटाएँ।',
    ],
    'cash_collection_overdue' => [
        'title' => 'ऑर्डर :order की नकद राशि दर्ज नहीं हुई',
        'message' => 'ऑर्डर :order डिलीवर हो गया, पर :amount नकद को वसूला गया दर्ज नहीं किया गया।',
    ],
    'delivery_failed' => [
        'title' => 'ऑर्डर :order की डिलीवरी विफल रही',
        'message' => 'ड्राइवर ऑर्डर :order डिलीवर नहीं कर सका। दूसरी डिलीवरी तय करें या खरीदार से संपर्क करें।',
    ],
    'stock_running_low' => [
        'title' => ':product का स्टॉक कम हो रहा है',
        'message' => ':product (SKU :sku) के केवल :available बचे हैं।',
    ],
    'variant_sold_out' => [
        'title' => ':product बिक गया',
        'message' => ':product (SKU :sku) पूरा बिक गया।',
    ],
    'return_requested' => [
        'title' => 'ऑर्डर :order के लिए रिटर्न का अनुरोध',
        'message' => 'एक खरीदार ने ऑर्डर :order की चीज़ें लौटाने का अनुरोध किया।',
    ],
    'waiting' => [
        'title' => 'और अलर्ट आपकी प्रतीक्षा में हैं',
        'message' => 'इस घंटे ईमेल से भेजे जाने से अधिक अलर्ट आए। सभी अपने डैशबोर्ड में देखें।',
    ],
    'see_dashboard' => 'इसे अपने स्टोर डैशबोर्ड में देखें।',
];
