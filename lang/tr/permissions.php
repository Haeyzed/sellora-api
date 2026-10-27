<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => 'Hizmet şartları gibi satıcıların kabul ettiği yasal belgeleri hazırlayın, düzenleyin ve yayınlayın.',
    ],
    'stores' => [
        'view' => 'Her mağazayı, durumunu, planını, özellik izinlerini ve limit geçersiz kılmalarını görün.',
        'manage' => 'Mağazaları askıya alın, yeniden etkinleştirin, kapatın ve geri yükleyin ve kurulumu başarısız olan bir mağazayı kurmayı yeniden deneyin.',
        'grant' => 'Mağazalara planlarının dışında modül ve entegrasyonlar verin, kullanım limitlerini değiştirin.',
        'export' => 'Her müşterinin kişisel verileri de dahil olmak üzere mağazanın tüm verilerini dışa aktarın.',
    ],
    'staff' => [
        'view' => 'Ekibi görün: personel üyeleri, rolleri ve bekleyen davetler.',
        'invite' => 'İnsanları ekibe katılmaya davet edin ve davetiyeleri yeniden gönderin veya iptal edin.',
        'manage' => 'Personel üyelerinin rollerini değiştirin ve bunları devre dışı bırakın veya yeniden etkinleştirin.',
    ],
    'catalog' => [
        'view' => 'Kataloğa bakın: taslaklar, arşivlenmiş öğeler ve çöp kutusu da dahil olmak üzere markalar, kategoriler ve ürünler.',
        'manage' => 'Markaları, kategorileri ve ürünleri oluşturup değiştirin, bunları çöp kutusuna taşıyın ve geri yükleyin.',
    ],
    'inventory' => [
        'view' => 'Stok seviyelerini, konumlarını ve her stok değişiminin geçmişini görün.',
        'adjust' => 'Stok alın, stok sayımlarını düzeltin ve her bir varyantın stoğunun işlenme şeklini değiştirin.',
        'costs' => 'Her birimin mağazaya maliyetini görün ve değiştirin. Maliyetler dahilidir ve müşterilere asla gösterilmez.',
    ],
    'customers' => [
        'view' => 'Devre dışı bırakılan ve çöpe atılan hesaplar da dahil olmak üzere müşterileri, profillerini, adreslerini, personel notlarını ve gruplarını görün.',
        'manage' => 'Müşteri profillerini değiştirin, hesapları devre dışı bırakın, yeniden etkinleştirin, çöpe atın ve geri yükleyin, not ekleyin veya silin ve müşteri gruplarını yönetin.',
        'tax_exemption' => 'Müşteriyi vergiden muaf tutun veya vergi kimlik numaralarını kontrol ettikten sonra bu durumu sonlandırın.',
        'privacy' => 'Müşterinin isteği üzerine, müşterinin tüm kişisel verilerini indirin veya tamamen silin.',
    ],
    'promotions' => [
        'view' => 'Promosyonları, kodlarını ve ne sıklıkta kullanıldığını görün ve bir sepetin ne getireceğini önizleyin.',
        'manage' => 'Promosyonları oluşturun, değiştirin, etkinleştirin, devre dışı bırakın ve arşivleyin, kodlarını ekleyin, oluşturun veya değiştirin.',
    ],
    'shipping' => [
        'view' => 'Gönderim bölgelerini, yöntemlerini ve ücretlerini görün ve bir sepetin gönderim için ne kadar ödeyeceğini önizleyin.',
        'manage' => 'Gönderim bölgelerini oluşturun, değiştirin ve silin; gönderim yöntemlerini ve ücretlerini oluşturun, değiştirin ve arşivleyin.',
    ],
    'orders' => [
        'view' => 'Siparişleri, satırlarını, ödemelerini, geçmişini ve gönderilerini görün.',
        'manage' => 'Siparişleri iptal edin, notlar ve etiketler ekleyin ve alıcıların iletişim bilgilerini ve teslimat adreslerini düzeltin.',
        'fulfil' => 'Gönderiler oluşturun, takip ekleyin ve kalanları, teslim alınmaya hazır olanları veya ulaşanları işaretleyin.',
    ],
    'invoices' => [
        'view' => 'Alıcının bilgileriyle birlikte faturaları ve kredi notlarını görün ve PDF’lerini indirin.',
    ],
    'payments' => [
        'view' => 'Ödemeleri, geri ödemeleri ve ödeme ağ geçitlerinin nasıl bağlandığını görün.',
        'confirm' => 'Bir banka havalesinin ulaştığını veya teslimatta nakit paranın tahsil edildiğini doğrulayın ve ağ geçidinden ödeme hakkında tekrar bilgi isteyin.',
        'refund' => 'Ödemeleri ağ geçidi aracılığıyla veya elden kaydedilerek iade edin.',
        'manage_gateways' => 'Ödeme ağ geçitlerini ve ödeme yöntemlerini bağlayın, bağlantısını kesin ve ayarlayın.',
    ],
    'pricing' => [
        'view' => 'Vergi sınıflarını, vergi oranlarını, her bir varyantın hangi sınıfta olduğunu ve mağazanın para birimlerini ve döviz kurlarını görün.',
        'manage' => 'Vergi sınıflarını, vergi oranlarını, varyantların vergi sınıflarını, mağazanın para birimlerini, döviz kurlarını ve sabit fiyatları değiştirin.',
    ],
    'settings' => [
        'view' => 'Mağazanın ayarlarına bakın: ad, ülke, para birimi, diller, vergi modu, birimler ve iletişim bilgileri.',
        'manage' => 'Mağazanın ayarlarını değiştirin. Personelin sahibinde kalması için iki faktörlü kimlik doğrulamanın zorunlu kılınması.',
    ],
    'roles' => [
        'view' => 'Mağazanın rollerini ve her birinin neye izin verdiğini görün.',
        'manage' => 'Rolleri oluşturun, yeniden adlandırın, silin ve her birinin neye izin vereceğini seçin.',
    ],
    'delivery' => [
        'view' => 'Mağazanın sürücülerini ve teslimatlarını görüntüleyin.',
    ],
    'drivers' => [
        'manage' => 'Sürücü ekleyin, değiştirin, devre dışı bırakın ve silin; PIN seçmeleri için kod verin.',
    ],
];
