<?php

declare(strict_types=1);

return [
    'new_order' => [
        'title' => 'Pesanan baru :order',
        'message' => 'Pesanan :order dibuat sebesar :amount.',
    ],
    'payment_needs_review' => [
        'title' => 'Pembayaran pesanan :order perlu ditinjau',
        'message' => 'Pembayaran :amount untuk pesanan :order tidak sesuai dengan yang diharapkan. Periksa sebelum memproses pesanan.',
    ],
    'payment_unresolved' => [
        'title' => 'Pembayaran pesanan :order belum terselesaikan',
        'message' => 'Gateway belum menyelesaikan pembayaran :amount untuk pesanan :order. Periksa dengan gateway.',
    ],
    'payment_overpaid' => [
        'title' => 'Pesanan :order dibayar lebih',
        'message' => 'Pembeli membayar :amount lebih dari harga pesanan :order. Kembalikan selisihnya.',
    ],
    'payment_disputed' => [
        'title' => 'Sebuah pembayaran disengketakan',
        'message' => 'Seorang pembeli menyengketakan pembayaran melalui :gateway. Tanggapi sengketa di dasbor gateway sebelum batas waktunya.',
    ],
    'payment_gateway_needs_reconnecting' => [
        'title' => 'Hubungkan ulang :gateway',
        'message' => ':gateway menolak kunci toko. Pembayaran melaluinya tertunda sampai Anda menghubungkannya kembali.',
    ],
    'order_paid_after_cancellation' => [
        'title' => 'Pesanan yang dibatalkan :order telah dibayar',
        'message' => 'Pembayaran untuk pesanan :order masuk setelah dibatalkan. Pesanan tetap dibatalkan; kembalikan pembayarannya.',
    ],
    'order_refunded_beyond_paid' => [
        'title' => 'Pesanan :order dikembalikan melebihi yang dibayar',
        'message' => 'Pengembalian dana pesanan :order melebihi jumlah yang dibayar pembeli. Periksa pengembalian dana pesanan.',
    ],
    'refund_needs_review' => [
        'title' => 'Pengembalian dana pesanan :order perlu ditinjau',
        'message' => 'Pengembalian dana :amount untuk pesanan :order perlu diperiksa: jawaban gateway tidak jelas.',
    ],
    'refund_failed' => [
        'title' => 'Pengembalian dana pesanan :order gagal',
        'message' => 'Pengembalian dana :amount untuk pesanan :order gagal. Kembalikan dana pembeli dengan cara lain, atau coba lagi.',
    ],
    'refund_still_pending' => [
        'title' => 'Pengembalian dana pesanan :order masih tertunda',
        'message' => 'Pengembalian dana :amount untuk pesanan :order sudah berhari-hari menunggu di gateway. Periksa dengan gateway.',
    ],
    'refund_succeeded_after_failing' => [
        'title' => 'Pengembalian dana pesanan :order yang gagal ternyata berhasil',
        'message' => 'Pengembalian dana :amount untuk pesanan :order, yang dilaporkan gagal, ternyata berhasil. Jika Anda mengembalikan lagi, pembeli menerima pengembalian dua kali.',
    ],
    'payment_hold_keeps_failing' => [
        'title' => 'Pembayaran pesanan :order tidak dapat diselesaikan',
        'message' => 'Penyelesaian pembayaran :method sebesar :amount untuk pesanan :order terus gagal. Dicoba lagi setiap jam.',
    ],
    'order_payments_keep_failing' => [
        'title' => 'Pembayaran pesanan :order tidak dapat dicatat',
        'message' => 'Pencatatan pembayaran pesanan :order terus gagal. Dicoba lagi setiap jam.',
    ],
    'committed_payment_refund_failed' => [
        'title' => 'Uang untuk pesanan yang dibatalkan :order tidak dapat dikembalikan',
        'message' => 'Pembayaran :method sebesar :amount untuk pesanan yang dibatalkan :order tidak dapat dikembalikan. Dicoba lagi setiap jam, dan pesanan masih berutang pengembalian dana.',
    ],
    'exchange_settlement_refused' => [
        'title' => 'Penukaran untuk pesanan :order tidak diselesaikan',
        'message' => 'Pesanan pengganti :order telah dibayar, tetapi penyelesaian pengembaliannya ditolak (:reason). Selesaikan lagi dari pengembalian.',
    ],
    'cash_collection_overdue' => [
        'title' => 'Uang tunai untuk pesanan :order belum dicatat',
        'message' => 'Pesanan :order sudah diantar, tetapi :amount tunai belum dicatat sebagai diterima.',
    ],
    'delivery_failed' => [
        'title' => 'Pengiriman pesanan :order gagal',
        'message' => 'Pengemudi tidak dapat mengantar pesanan :order. Atur pengiriman lain atau hubungi pembeli.',
    ],
    'stock_running_low' => [
        'title' => 'Stok :product menipis',
        'message' => 'Hanya tersisa :available dari :product (SKU :sku).',
    ],
    'variant_sold_out' => [
        'title' => ':product habis terjual',
        'message' => ':product (SKU :sku) habis terjual.',
    ],
    'return_requested' => [
        'title' => 'Pengembalian diminta untuk pesanan :order',
        'message' => 'Seorang pembeli meminta untuk mengembalikan barang dari pesanan :order.',
    ],
    'waiting' => [
        'title' => 'Ada peringatan lain yang menunggu',
        'message' => 'Jam ini masuk lebih banyak peringatan daripada yang dikirim lewat email. Lihat semuanya di dasbor Anda.',
    ],
    'see_dashboard' => 'Lihat di dasbor toko Anda.',
];
