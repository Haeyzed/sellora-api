<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Kelola dokumen hukum',
            'description' => 'Buat draf, edit, dan publikasikan dokumen hukum yang diterima pedagang, seperti persyaratan layanan.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Lihat toko',
            'description' => 'Lihat setiap toko, statusnya, paketnya, pemberian fitur, dan penggantian batas.',
        ],
        'manage' => [
            'label' => 'Kelola toko',
            'description' => 'Tangguhkan, aktifkan kembali, tutup dan pulihkan toko, dan coba lagi menyiapkan toko yang pengaturannya gagal.',
        ],
        'grant' => [
            'label' => 'Berikan fitur dan batas',
            'description' => 'Berikan modul dan integrasi toko di luar paketnya, dan ubah batas penggunaannya.',
        ],
        'export' => [
            'label' => 'Ekspor data toko',
            'description' => 'Ekspor semua data toko, termasuk data pribadi setiap pelanggan.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Lihat tim',
            'description' => 'Lihat tim: anggota staf, peran mereka, dan undangan yang menunggu keputusan.',
        ],
        'invite' => [
            'label' => 'Undang staf',
            'description' => 'Undang orang untuk bergabung dengan tim, dan kirim ulang atau batalkan undangan.',
        ],
        'manage' => [
            'label' => 'Kelola staf',
            'description' => 'Ubah peran anggota staf, dan nonaktifkan atau aktifkan kembali mereka.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Lihat katalog',
            'description' => 'Lihat katalog: merek, kategori dan produk, termasuk konsep, item arsip, dan sampah.',
        ],
        'manage' => [
            'label' => 'Kelola katalog',
            'description' => 'Buat dan ubah merek, kategori, dan produk, pindahkan ke sampah, dan pulihkan.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Lihat stok',
            'description' => 'Lihat level stok, lokasi dan riwayat setiap perubahan stok.',
        ],
        'adjust' => [
            'label' => 'Sesuaikan stok',
            'description' => 'Terima stok, perbaiki jumlah stok, dan ubah cara penanganan stok setiap varian.',
        ],
        'costs' => [
            'label' => 'Biaya per unit',
            'description' => 'Lihat dan ubah harga setiap unit di toko. Biaya bersifat internal dan tidak pernah diperlihatkan kepada pelanggan.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Lihat pelanggan',
            'description' => 'Lihat pelanggan, profil mereka, alamat, catatan staf dan grup, termasuk akun yang dinonaktifkan dan dibuang.',
        ],
        'manage' => [
            'label' => 'Kelola pelanggan',
            'description' => 'Ubah profil pelanggan, nonaktifkan, aktifkan kembali, buang dan pulihkan akun, tambah atau hapus catatan, dan kelola grup pelanggan.',
        ],
        'tax_exemption' => [
            'label' => 'Pembebasan pajak',
            'description' => 'Menjadikan pelanggan bebas pajak, atau mengakhirinya, setelah memeriksa nomor pajaknya.',
        ],
        'privacy' => [
            'label' => 'Permintaan data pelanggan',
            'description' => 'Unduh semua data pribadi pelanggan, atau hapus selamanya, atas permintaan pelanggan.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Lihat promosi',
            'description' => 'Lihat promosi, kodenya dan seberapa sering digunakan, dan lihat pratinjau apa yang akan didapat keranjang.',
        ],
        'manage' => [
            'label' => 'Kelola promosi',
            'description' => 'Membuat, mengubah, mengaktifkan, menonaktifkan, dan mengarsipkan promosi, serta menambah, membuat, atau mengubah kodenya.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Lihat pengiriman',
            'description' => 'Lihat zona pengiriman, metode dan tarifnya, dan pratinjau berapa keranjang yang akan dibayar untuk pengiriman.',
        ],
        'manage' => [
            'label' => 'Kelola pengiriman',
            'description' => 'Membuat, mengubah, dan menghapus zona pengiriman, serta membuat, mengubah, dan mengarsipkan metode pengiriman serta tarifnya.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Lihat pesanan',
            'description' => 'Lihat pesanan, jalurnya, pembayaran, riwayat, dan pengirimannya.',
        ],
        'manage' => [
            'label' => 'Kelola pesanan',
            'description' => 'Batalkan pesanan, tambahkan catatan dan tag, serta perbaiki detail kontak pembeli dan alamat pengiriman.',
        ],
        'fulfil' => [
            'label' => 'Penuhi pesanan',
            'description' => 'Buat pengiriman, tambahkan pelacakan, dan tandai apa yang tersisa, siap diambil, atau telah tiba.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Lihat faktur',
            'description' => 'Lihat faktur dan catatan kredit dengan detail pembeli di dalamnya, dan unduh file PDF-nya.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Lihat pembayaran',
            'description' => 'Lihat pembayaran, pengembalian dana, dan cara gateway pembayaran terhubung.',
        ],
        'confirm' => [
            'label' => 'Konfirmasi pembayaran',
            'description' => 'Konfirmasikan bahwa transfer bank telah tiba atau pembayaran tunai telah diambil, dan tanyakan lagi kepada gateway tentang pembayaran.',
        ],
        'refund' => [
            'label' => 'Kembalikan dana pembayaran',
            'description' => 'Pembayaran pengembalian dana, melalui gateway atau seperti yang dicatat dengan tangan.',
        ],
        'manage_gateways' => [
            'label' => 'Kelola gateway pembayaran',
            'description' => 'Hubungkan, putuskan sambungan, dan atur gateway pembayaran dan metode pembayaran.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Lihat pajak dan mata uang',
            'description' => 'Lihat kelas pajak, tarif pajak, di kelas mana setiap varian berada, serta mata uang dan nilai tukar toko.',
        ],
        'manage' => [
            'label' => 'Kelola pajak dan mata uang',
            'description' => 'Ubah kelas pajak, tarif pajak, kelas pajak varian, mata uang toko, nilai tukar, dan harga tetap.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Lihat pengaturan',
            'description' => 'Lihat pengaturan toko: nama, negara, mata uang, bahasa, mode pajak, unit, dan detail kontak.',
        ],
        'manage' => [
            'label' => 'Kelola pengaturan',
            'description' => 'Ubah pengaturan toko. Memerlukan otentikasi dua faktor untuk staf tetap bersama pemiliknya.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Lihat peran',
            'description' => 'Lihat peran toko dan apa yang diperbolehkan oleh masing-masing toko.',
        ],
        'manage' => [
            'label' => 'Kelola peran',
            'description' => 'Buat, ganti nama, dan hapus peran, dan pilih peran yang diizinkan masing-masing peran.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Lihat pengantaran',
            'description' => 'Lihat pengemudi dan pengiriman toko.',
        ],
        'manage' => [
            'label' => 'Kelola pengantaran',
            'description' => 'Memberikan pengiriman ke pengemudi, serta memajukan, mengulang, menarik kembali, atau membatalkan pengantaran mereka.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Catat uang tunai kurir',
                'description' => 'Mencatat uang tunai yang diserahkan pengemudi, tidak pernah lebih dari yang mereka pegang.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Kelola kurir',
            'description' => 'Tambah, ubah, nonaktifkan, dan hapus pengemudi, serta beri mereka kode untuk memilih PIN.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'Lihat retur',
            'description' => 'Melihat retur, foto dan riwayatnya, serta pengaturan retur.',
        ],
        'manage' => [
            'label' => 'Kelola retur',
            'description' => 'Membuat, menyetujui, menolak, menerima, memeriksa, dan membatalkan retur, serta mengubah pengaturan retur.',
        ],
        'exchange' => [
            'label' => 'Konfirmasi penukaran',
            'description' => 'Konfirmasi penukaran sebuah retur: ini membuat pesanan penggantinya dan menyelesaikan nilai barang yang diretur.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Lihat konten etalase',
            'description' => 'Melihat halaman, menu, banner, dan pengaturan etalase toko.',
        ],
        'manage' => [
            'label' => 'Kelola konten etalase',
            'description' => 'Menambah, mengubah, menerbitkan, dan menghapus halaman, menu, dan banner toko, serta mengubah pengaturan etalase.',
        ],
    ],
];
