<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Döviz kurları :hours saattir yenilenmedi',
        'message' => 'En az :hours saattir hiçbir döviz kuru alımı başarılı olmadı. Kurlar :stale_hours saatlik olduğunda mağazalar ek para birimlerini sunmayı bırakır. Döviz kuru sağlayıcısını kontrol edin.',
    ],
    'store_setup_failed' => [
        'title' => ':store mağazasının kurulumu başarısız oldu',
        'message' => ':store (:store_id) mağazasının kurulumu tüm denemelerden sonra başarısız oldu. Neden giderildiğinde mağaza listesinden yeniden deneyin.',
    ],
    'stock_levels_mismatch' => [
        'title' => ':store içindeki stok düzeyleri kayıtlarıyla uyuşmuyor',
        'message' => 'Planlı kontrol, :store (:store_id) mağazasında stok hareketi kayıtlarıyla uyuşmayan stok düzeyleri buldu (:count). Ayrıntılar kontrolün günlüğünde.',
    ],
    'invoice_balances_mismatch' => [
        'title' => ':store içindeki faturalar tutulan satışlarla uyuşmuyor',
        'message' => 'Planlı kontrol, :store (:store_id) mağazasında iade faturaları tutulan satış parasıyla veya faturalanan vergiyle uyuşmayan faturalar buldu (:count). Ayrıntılar kontrolün günlüğünde.',
    ],
    'see_dashboard' => 'Daha fazlası için :app platform paneline giriş yapın.',
];
