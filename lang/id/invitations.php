<?php

declare(strict_types=1);

return [
    'staff' => [
        'subject' => ':inviter mengundang Anda untuk bergabung dengan tim toko mereka',
        'reason' => ':inviter mengundang Anda untuk membantu menjalankan toko mereka. Terima untuk memilih kata sandi Anda dan masuk.',
        'action' => 'Terima undangannya',
        'expiry' => 'Tautan ini berfungsi satu kali dan habis masa berlakunya dalam :days hari.',
        'ignore' => 'Jika Anda tidak mengharapkannya, Anda dapat mengabaikan email ini; tidak ada akun yang dibuat.',
    ],
    'platform_admin' => [
        'subject' => ':inviter mengundang Anda untuk bergabung dengan tim :app',
        'reason' => ':inviter mengundang Anda untuk membantu menjalankan platform :app. Terima untuk memilih kata sandi Anda.',
        'action' => 'Terima undangannya',
        'expiry' => 'Tautan ini berfungsi satu kali dan habis masa berlakunya dalam :days hari.',
        'two_factor' => 'Anda memerlukan aplikasi autentikator: autentikasi dua faktor diperlukan sebelum Anda dapat menggunakan akun Anda.',
        'ignore' => 'Jika Anda tidak mengharapkannya, Anda dapat mengabaikan email ini; tidak ada akun yang dibuat.',
    ],
];
