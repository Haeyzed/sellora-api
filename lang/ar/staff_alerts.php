<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'طلب جديد :order',
        'message' => 'تم تقديم الطلب :order بمبلغ :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'دفعة للطلب :order تحتاج إلى مراجعة',
        'message' => 'دفعة بمبلغ :amount للطلب :order لم تطابق المتوقع. راجعها قبل تجهيز الطلب.',
    ],
    'payment_unresolved' => [
        'title' => 'دفعة للطلب :order لم تُحسم',
        'message' => 'لم تُكمل بوابة الدفع الدفعة بمبلغ :amount للطلب :order. تحقق منها لدى البوابة.',
    ],
    'payment_overpaid' => [
        'title' => 'دُفع للطلب :order أكثر من قيمته',
        'message' => 'دفع المشتري :amount أكثر من قيمة الطلب :order. أعد الفرق.',
    ],
    'payment_disputed' => [
        'title' => 'تم الاعتراض على دفعة',
        'message' => 'اعترض مشترٍ على دفعة تمت عبر :gateway. ردّ على الاعتراض في لوحة البوابة قبل موعده النهائي.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'أعد ربط :gateway',
        'message' => 'رفضت :gateway مفاتيح المتجر. تبقى المدفوعات عبرها معلقة حتى تعيد ربطها.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'تم دفع الطلب الملغى :order',
        'message' => 'وصلت دفعة للطلب :order بعد إلغائه. يبقى الطلب ملغى؛ أعد الدفعة.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'استُرد للطلب :order أكثر مما دُفع',
        'message' => 'مجموع المبالغ المستردة للطلب :order أكبر مما دفعه المشتري. راجع استردادات الطلب.',
    ],
    'refund_needs_review' => [
        'title' => 'استرداد للطلب :order يحتاج إلى مراجعة',
        'message' => 'استرداد مبلغ :amount للطلب :order يحتاج إلى تحقق: كان رد البوابة غير واضح.',
    ],
    'refund_failed' => [
        'title' => 'فشل استرداد للطلب :order',
        'message' => 'فشل استرداد مبلغ :amount للطلب :order. أعد المبلغ للمشتري بطريقة أخرى أو حاول مجددًا.',
    ],
    'refund_still_pending' => [
        'title' => 'استرداد للطلب :order لا يزال معلقًا',
        'message' => 'استرداد مبلغ :amount للطلب :order ينتظر لدى البوابة منذ أيام. تحقق منه لدى البوابة.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'تم استرداد فاشل للطلب :order',
        'message' => 'تم في النهاية استرداد مبلغ :amount للطلب :order الذي أُبلغ بفشله. إذا أعدت المبلغ مرة أخرى فقد استرد المشتري مرتين.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'لا يمكن إتمام دفعة للطلب :order',
        'message' => 'يستمر فشل إتمام دفعة :method بمبلغ :amount للطلب :order. تُعاد المحاولة كل ساعة.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'لا يمكن تسجيل مدفوعات الطلب :order',
        'message' => 'يستمر فشل تسجيل مدفوعات الطلب :order. تُعاد المحاولة كل ساعة.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'لا يمكن إعادة أموال الطلب الملغى :order',
        'message' => 'تعذرت إعادة دفعة :method بمبلغ :amount للطلب الملغى :order. تُعاد المحاولة كل ساعة، ولا يزال الطلب مستحقًا للاسترداد.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'لم تتم تسوية استبدال للطلب :order',
        'message' => 'تم دفع الطلب البديل :order، لكن رُفضت تسوية الإرجاع الخاص به (:reason). سوّه مجددًا من الإرجاع.',
    ],
    'cash_collection_overdue' => [
        'title' => 'لم يُسجل النقد للطلب :order',
        'message' => 'تم تسليم الطلب :order، لكن مبلغ :amount نقدًا لم يُسجل كمحصّل.',
    ],
    'delivery_failed' => [
        'title' => 'فشل توصيل الطلب :order',
        'message' => 'لم يتمكن السائق من توصيل الطلب :order. رتّب توصيلًا آخر أو تواصل مع المشتري.',
    ],
    'stock_running_low' => [
        'title' => 'مخزون :product ينفد',
        'message' => 'تبقى :available فقط من :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => 'نفد :product',
        'message' => 'نفد :product (SKU :sku).',
    ],
    'return_requested' => [
        'title' => 'طلب إرجاع للطلب :order',
        'message' => 'طلب مشترٍ إرجاع منتجات من الطلب :order.',
    ],
    'waiting' => [
        'title' => 'هناك تنبيهات أخرى بانتظارك',
        'message' => 'وصلت في هذه الساعة تنبيهات أكثر مما يُرسل بالبريد الإلكتروني. اطلع عليها كلها في لوحة التحكم.',
    ],
    'see_dashboard' => 'اطلع عليه في لوحة تحكم متجرك.',
];
