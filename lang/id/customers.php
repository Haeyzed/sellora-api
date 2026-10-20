<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'Konfirmasikan alamat email Anda',
        'reason' => 'Konfirmasikan bahwa ini adalah alamat email Anda, sehingga Anda dapat melihat pesanan Anda sebelumnya dan mengunduh data Anda.',
        'action' => 'Konfirmasikan email saya',
        'expiry' => 'Tautan ini berfungsi satu kali dan habis masa berlakunya dalam :hours jam.',
        'ignore' => 'Jika Anda tidak membuat akun, Anda dapat mengabaikan email ini.',
    ],
    'email_change' => [
        'subject' => 'Konfirmasikan alamat email baru Anda',
        'reason' => 'Anda meminta untuk menggunakan alamat ini untuk akun Anda. Buka tautan ini saat masuk untuk melakukan perubahan.',
        'action' => 'Gunakan email ini',
        'expiry' => 'Tautan ini berfungsi satu kali dan habis masa berlakunya dalam :minutes menit.',
        'ignore' => 'Jika Anda tidak memintanya, Anda dapat mengabaikan email ini; tidak ada yang berubah.',
    ],
    'marketing_consent' => [
        'email' => 'Kirimi saya berita dan penawaran dari :store melalui email. Saya dapat berhenti berlangganan kapan saja.',
        'sms' => 'Kirimi saya berita dan penawaran dari :store melalui pesan teks. Saya dapat berhenti berlangganan kapan saja.',
    ],
    'email_changed' => [
        'subject' => 'Alamat email akun Anda telah diubah',
        'reason' => 'Alamat email akun Anda telah diubah, dan semua perangkat lainnya telah logout. Email tentang akun Anda sekarang masuk ke alamat baru.',
        'help' => 'Jika Anda tidak melakukan perubahan ini, segera hubungi toko.',
    ],
];
