<?php

declare(strict_types=1);

return [
    'email_verification' => [
        'subject' => 'E-posta adresinizi onaylayın',
        'reason' => 'Geçmiş siparişlerinizi görebilmeniz ve verilerinizi indirebilmeniz için bunun e-posta adresiniz olduğunu doğrulayın.',
        'action' => 'E-postamı onayla',
        'expiry' => 'Bu bağlantı bir kez çalışır ve süresi :hours saat içinde dolar.',
        'ignore' => 'Hesap oluşturmadıysanız bu e-postayı yok sayabilirsiniz.',
    ],
    'email_change' => [
        'subject' => 'Yeni e-posta adresinizi onaylayın',
        'reason' => 'Hesabınız için bu adresi kullanmayı istediniz. Değişikliği yapmak için oturum açtığınızda bu bağlantıyı açın.',
        'action' => 'Bu e-postayı kullan',
        'expiry' => 'Bu bağlantı bir kez çalışır ve süresi :minutes dakika içinde dolar.',
        'ignore' => 'Bunu siz talep etmediyseniz bu e-postayı görmezden gelebilirsiniz; hiçbir şey değişmez.',
    ],
    'marketing_consent' => [
        'email' => ':store\'dan haber ve teklifleri bana e-postayla gönder. İstediğim zaman abonelikten çıkabilirim.',
        'sms' => ':store\'dan haber ve teklifleri bana kısa mesajla gönder. İstediğim zaman abonelikten çıkabilirim.',
    ],
    'email_changed' => [
        'subject' => 'Hesabınızın e-posta adresi değiştirildi',
        'reason' => 'Hesabınızın e-posta adresi değiştirildi ve diğer tüm cihazlardan çıkış yapıldı. Hesabınızla ilgili e-postalar artık yeni adrese gidiyor.',
        'help' => 'Bu değişikliği siz yapmadıysanız hemen mağazayla iletişime geçin.',
    ],
];
