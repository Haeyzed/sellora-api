<?php

declare(strict_types=1);

return [
    'closed' => [
        'subject' => ':store mağazanız kapalı',
        'line' => ':store mağazanız kapalı. Artık kimseye açılmıyor ve herkesin oturumu kapatıldı. Verileri :date tarihinde tamamen silinecek.',
    ],
    'reminder' => [
        'subject' => ':store mağazanız :date tarihinde silinecek',
        'line' => ':store mağazanız kapatıldı. Verileri :date tarihinde tamamen silinecek ve bundan sonra kurtarılamaz.',
    ],
    'export' => 'Mağazanızın verilerinin bir kopyasına ihtiyacınız varsa veya mağazayı yeniden açmak istiyorsanız :date\'dan önce :app desteğiyle iletişime geçin: mağazayı geri yükleyebilirler, böylece her şeyi dışa aktarabilirsiniz.',
    'not_you' => 'Bu mağazayı kapatmadıysanız hemen :app desteğiyle iletişime geçin.',
];
