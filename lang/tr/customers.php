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
    'social_link' => [
        'subject' => 'Birisi hesabınıza bir oturum açma yöntemi bağlıyor',
        'reason' => 'Birisi bu e-posta adresini kullanarak :provider ile giriş yaptı ve hesabınıza bağlanmasını istedi. Yalnızca bunu siz yaptıysanız onaylayın: bağlandıktan sonra :provider ile giriş yapıldığında hesabınız açılır.',
        'action' => 'Evet, :provider bağla',
        'expiry' => 'Bu bağlantı bir kez çalışır ve :minutes dakika içinde sona erer.',
        'ignore' => 'Bu siz değilseniz bu e-postayı görmezden gelin: hiçbir şey bağlanmaz ve hesabınız olduğu gibi kalır.',
    ],
    'erasure' => [
        'subject' => 'Hesabınızın silinmesini onaylayın',
        'reason' => 'Bu mağazada hesabınızı silmesini istediniz. Onaylamak için giriş yaparken bu bağlantıyı açın: mağazanın sizin hakkınızda tuttuğu her şey silinir ve bu işlem geri alınamaz.',
        'action' => 'Hesabımı sil',
        'expiry' => 'Bu bağlantı bir kez çalışır ve :minutes dakika içinde sona erer.',
        'ignore' => 'Bunu siz istemediyseniz bu e-postayı görmezden gelin: hiçbir şey silinmez. Başka biri hesabınıza giriş yapmış olabilir, bu yüzden diğer cihazlarınızdan çıkış yapın.',
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
