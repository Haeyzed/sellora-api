<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Buat draf, edit, dan publikasikan dokumen hukum yang diterima pedagang, seperti persyaratan layanan.',
    ],
    'stores' => [
        'view' => 'Lihat setiap toko, statusnya, paketnya, pemberian fitur, dan penggantian batas.',
        'manage' => 'Tangguhkan, aktifkan kembali, tutup dan pulihkan toko, dan coba lagi menyiapkan toko yang pengaturannya gagal.',
        'grant' => 'Berikan modul dan integrasi toko di luar paketnya, dan ubah batas penggunaannya.',
        'export' => 'Ekspor semua data toko, termasuk data pribadi setiap pelanggan.',
    ],
    'staff' => [
        'view' => 'Lihat tim: anggota staf, peran mereka, dan undangan yang menunggu keputusan.',
        'invite' => 'Undang orang untuk bergabung dengan tim, dan kirim ulang atau batalkan undangan.',
        'manage' => 'Ubah peran anggota staf, dan nonaktifkan atau aktifkan kembali mereka.',
    ],
    'catalog' => [
        'view' => 'Lihat katalog: merek, kategori dan produk, termasuk konsep, item arsip, dan sampah.',
        'manage' => 'Buat dan ubah merek, kategori, dan produk, pindahkan ke sampah, dan pulihkan.',
    ],
    'inventory' => [
        'view' => 'Lihat level stok, lokasi dan riwayat setiap perubahan stok.',
        'adjust' => 'Terima stok, perbaiki jumlah stok, dan ubah cara penanganan stok setiap varian.',
        'costs' => 'Lihat dan ubah harga setiap unit di toko. Biaya bersifat internal dan tidak pernah diperlihatkan kepada pelanggan.',
    ],
    'customers' => [
        'view' => 'Lihat pelanggan, profil mereka, alamat, catatan staf dan grup, termasuk akun yang dinonaktifkan dan dibuang.',
        'manage' => 'Ubah profil pelanggan, nonaktifkan, aktifkan kembali, buang dan pulihkan akun, tambah atau hapus catatan, dan kelola grup pelanggan.',
        'tax_exemption' => 'Menjadikan pelanggan bebas pajak, atau mengakhirinya, setelah memeriksa nomor pajaknya.',
        'privacy' => 'Unduh semua data pribadi pelanggan, atau hapus selamanya, atas permintaan pelanggan.',
    ],
    'promotions' => [
        'view' => 'Lihat promosi, kodenya dan seberapa sering digunakan, dan lihat pratinjau apa yang akan didapat keranjang.',
        'manage' => 'Membuat, mengubah, mengaktifkan, menonaktifkan, dan mengarsipkan promosi, serta menambah, membuat, atau mengubah kodenya.',
    ],
    'shipping' => [
        'view' => 'Lihat zona pengiriman, metode dan tarifnya, dan pratinjau berapa keranjang yang akan dibayar untuk pengiriman.',
        'manage' => 'Membuat, mengubah, dan menghapus zona pengiriman, serta membuat, mengubah, dan mengarsipkan metode pengiriman serta tarifnya.',
    ],
    'orders' => [
        'view' => 'Lihat pesanan, jalurnya, pembayaran, riwayat, dan pengirimannya.',
        'manage' => 'Batalkan pesanan, tambahkan catatan dan tag, serta perbaiki detail kontak pembeli dan alamat pengiriman.',
        'fulfil' => 'Buat pengiriman, tambahkan pelacakan, dan tandai apa yang tersisa, siap diambil, atau telah tiba.',
    ],
    'invoices' => [
        'view' => 'Lihat faktur dan catatan kredit dengan detail pembeli di dalamnya, dan unduh file PDF-nya.',
    ],
    'payments' => [
        'view' => 'Lihat pembayaran, pengembalian dana, dan cara gateway pembayaran terhubung.',
        'confirm' => 'Konfirmasikan bahwa transfer bank telah tiba atau pembayaran tunai telah diambil, dan tanyakan lagi kepada gateway tentang pembayaran.',
        'refund' => 'Pembayaran pengembalian dana, melalui gateway atau seperti yang dicatat dengan tangan.',
        'manage_gateways' => 'Hubungkan, putuskan sambungan, dan atur gateway pembayaran dan metode pembayaran.',
    ],
    'pricing' => [
        'view' => 'Lihat kelas pajak, tarif pajak, di kelas mana setiap varian berada, serta mata uang dan nilai tukar toko.',
        'manage' => 'Ubah kelas pajak, tarif pajak, kelas pajak varian, mata uang toko, nilai tukar, dan harga tetap.',
    ],
    'settings' => [
        'view' => 'Lihat pengaturan toko: nama, negara, mata uang, bahasa, mode pajak, unit, dan detail kontak.',
        'manage' => 'Ubah pengaturan toko. Memerlukan otentikasi dua faktor untuk staf tetap bersama pemiliknya.',
    ],
    'roles' => [
        'view' => 'Lihat peran toko dan apa yang diperbolehkan oleh masing-masing toko.',
        'manage' => 'Buat, ganti nama, dan hapus peran, dan pilih peran yang diizinkan masing-masing peran.',
    ],
];
