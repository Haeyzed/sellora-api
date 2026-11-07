<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Yeni sipariş :order',
        'message' => ':order numaralı sipariş :amount tutarında verildi.',
    ],
    'payment_needs_review' => [
        'title' => ':order siparişinin bir ödemesi incelenmeli',
        'message' => ':order siparişi için :amount tutarındaki bir ödeme beklenenle eşleşmedi. Siparişi hazırlamadan önce kontrol edin.',
    ],
    'payment_unresolved' => [
        'title' => ':order siparişinin bir ödemesi sonuçlanmadı',
        'message' => 'Ödeme sağlayıcısı :order siparişi için :amount tutarındaki ödemeyi tamamlamadı. Sağlayıcıyla kontrol edin.',
    ],
    'payment_overpaid' => [
        'title' => ':order siparişi fazla ödendi',
        'message' => 'Alıcı, :order siparişinin tutarından :amount fazla ödedi. Farkı iade edin.',
    ],
    'payment_disputed' => [
        'title' => 'Bir ödemeye itiraz edildi',
        'message' => 'Bir alıcı :gateway üzerinden yapılan bir ödemeye itiraz etti. Süre dolmadan sağlayıcının panelinden itiraza yanıt verin.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => ':gateway bağlantısını yenileyin',
        'message' => ':gateway mağazanın anahtarlarını reddetti. Bağlantıyı yenileyene kadar bu yolla yapılan ödemeler beklemede kalır.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'İptal edilen :order siparişi ödendi',
        'message' => ':order siparişi iptal edildikten sonra bir ödeme geldi. Sipariş iptal kalır; ödemeyi iade edin.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => ':order siparişi için ödenenden fazla iade yapıldı',
        'message' => ':order siparişinin iadeleri alıcının ödediğinden fazla. Siparişin iadelerini kontrol edin.',
    ],
    'refund_needs_review' => [
        'title' => ':order siparişinin bir iadesi incelenmeli',
        'message' => ':order siparişi için :amount tutarındaki iade kontrol edilmeli: sağlayıcının yanıtı belirsizdi.',
    ],
    'refund_failed' => [
        'title' => ':order siparişinin bir iadesi başarısız oldu',
        'message' => ':order siparişi için :amount tutarındaki iade başarısız oldu. Alıcıya başka bir yolla iade yapın veya yeniden deneyin.',
    ],
    'refund_still_pending' => [
        'title' => ':order siparişinin bir iadesi hâlâ beklemede',
        'message' => ':order siparişi için :amount tutarındaki iade günlerdir sağlayıcıda bekliyor. Sağlayıcıyla kontrol edin.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => ':order siparişinin başarısız iadesi gerçekleşti',
        'message' => ':order siparişi için başarısız bildirilen :amount tutarındaki iade sonunda gerçekleşti. Yeniden iade yaptıysanız alıcıya iki kez iade edildi.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => ':order siparişinin bir ödemesi tamamlanamıyor',
        'message' => ':order siparişi için :amount tutarındaki :method ödemesinin tamamlanması sürekli başarısız oluyor. Her saat yeniden deneniyor.',
    ],
    'order_payments_keep_failing' => [
        'title' => ':order siparişinin ödemeleri kaydedilemiyor',
        'message' => ':order siparişinin ödemelerinin kaydı sürekli başarısız oluyor. Her saat yeniden deneniyor.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'İptal edilen :order siparişinin parası geri verilemiyor',
        'message' => 'İptal edilen :order siparişi için :amount tutarındaki :method ödemesi geri verilemedi. Her saat yeniden deneniyor ve siparişin iadesi hâlâ bekliyor.',
    ],
    'exchange_settlement_refused' => [
        'title' => ':order siparişi için bir değişim sonuçlandırılamadı',
        'message' => 'Yedek sipariş :order ödendi, ancak iadesinin sonuçlandırılması reddedildi (:reason). İadeden yeniden sonuçlandırın.',
    ],
    'cash_collection_overdue' => [
        'title' => ':order siparişinin nakdi kaydedilmedi',
        'message' => ':order siparişi teslim edildi, ancak :amount tutarındaki nakit tahsil edildi olarak kaydedilmedi.',
    ],
    'delivery_failed' => [
        'title' => ':order siparişinin teslimatı başarısız oldu',
        'message' => 'Kurye :order siparişini teslim edemedi. Yeni bir teslimat ayarlayın veya alıcıyla iletişime geçin.',
    ],
    'stock_running_low' => [
        'title' => ':product azalıyor',
        'message' => ':product ürününden yalnızca :available adet kaldı (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product tükendi',
        'message' => ':product (SKU :sku) tükendi.',
    ],
    'return_requested' => [
        'title' => ':order siparişi için iade talep edildi',
        'message' => 'Bir alıcı :order siparişindeki ürünleri iade etmek istedi.',
    ],
    'waiting' => [
        'title' => 'Bekleyen başka uyarılar var',
        'message' => 'Bu saat içinde e-postayla gönderilenden fazla uyarı geldi. Hepsini panelinizde görün.',
    ],
    'see_dashboard' => 'Mağaza panelinizde görün.',
];
