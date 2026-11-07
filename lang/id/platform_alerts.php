<?php

declare(strict_types=1);

return [
    'exchange_rates_overdue' => [
        'title' => 'Kurs mata uang tidak diperbarui selama :hours jam',
        'message' => 'Tidak ada pengambilan kurs mata uang yang berhasil selama setidaknya :hours jam. Toko berhenti menawarkan mata uang tambahannya setelah kurs berumur :stale_hours jam. Periksa penyedia kurs mata uang.',
    ],
    'store_setup_failed' => [
        'title' => 'Penyiapan toko :store gagal',
        'message' => 'Penyiapan toko :store (:store_id) gagal setelah semua percobaan. Coba lagi dari daftar toko setelah penyebabnya diperbaiki.',
    ],
    'stock_levels_mismatch' => [
        'title' => 'Tingkat stok di :store tidak cocok dengan catatannya',
        'message' => 'Pemeriksaan terjadwal menemukan tingkat stok di toko :store (:store_id) yang tidak cocok dengan catatan pergerakan stoknya (:count). Detailnya ada di log pemeriksaan.',
    ],
    'invoice_balances_mismatch' => [
        'title' => 'Faktur di :store tidak cocok dengan penjualan yang disimpan',
        'message' => 'Pemeriksaan terjadwal menemukan faktur di toko :store (:store_id) yang nota kreditnya tidak sesuai dengan uang penjualan yang disimpan atau pajak yang ditagihkan (:count). Detailnya ada di log pemeriksaan.',
    ],
    'see_dashboard' => 'Masuk ke dasbor platform :app untuk selengkapnya.',
];
