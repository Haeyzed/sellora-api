<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner ingin menjadikan Anda pemilik toko mereka',
        'reason' => ':owner telah meminta Anda untuk mengambil alih sebagai pemilik toko. Sebagai pemilik, Anda dapat melakukan segalanya di toko, dan kontrak toko dengan :app menjadi milik Anda.',
        'terms' => 'Untuk menerimanya, masuk ke dasbor toko dan terima persyaratan layanan :app saat ini. Tidak ada yang berubah sampai Anda melakukannya.',
        'action' => 'Tinjau transfernya',
        'expiry' => 'Penawaran ini berakhir dalam :hours jam.',
        'ignore' => 'Jika Anda tidak ingin mengambil alih toko, abaikan email ini.',
    ],
    'completed' => [
        'subject' => 'Toko :store memiliki pemilik baru',
        'new_owner' => 'Anda sekarang adalah pemilik toko :store.',
        'previous_owner' => ':owner sekarang menjadi pemilik toko :store. Anda hanya menyimpan peran yang Anda pilih saat memulai transfer.',
        'not_you' => 'Jika Anda tidak mengharapkannya, segera hubungi dukungan :app.',
    ],
];
