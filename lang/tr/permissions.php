<?php

declare(strict_types=1);

return [
    'legal_documents' => [
        'manage' => [
            'label' => 'Yasal belgeleri yönet',
            'description' => 'Hizmet şartları gibi satıcıların kabul ettiği yasal belgeleri hazırlayın, düzenleyin ve yayınlayın.',
        ],
    ],
    'stores' => [
        'view' => [
            'label' => 'Mağazaları görüntüle',
            'description' => 'Her mağazayı, durumunu, planını, özellik izinlerini ve limit geçersiz kılmalarını görün.',
        ],
        'manage' => [
            'label' => 'Mağazaları yönet',
            'description' => 'Mağazaları askıya alın, yeniden etkinleştirin, kapatın ve geri yükleyin ve kurulumu başarısız olan bir mağazayı kurmayı yeniden deneyin.',
        ],
        'grant' => [
            'label' => 'Özellik ve limit tanımla',
            'description' => 'Mağazalara planlarının dışında modül ve entegrasyonlar verin, kullanım limitlerini değiştirin.',
        ],
        'export' => [
            'label' => 'Mağaza verilerini dışa aktar',
            'description' => 'Her müşterinin kişisel verileri de dahil olmak üzere mağazanın tüm verilerini dışa aktarın.',
        ],
    ],
    'staff' => [
        'view' => [
            'label' => 'Ekibi görüntüle',
            'description' => 'Ekibi görün: personel üyeleri, rolleri ve bekleyen davetler.',
        ],
        'invite' => [
            'label' => 'Personel davet et',
            'description' => 'İnsanları ekibe katılmaya davet edin ve davetiyeleri yeniden gönderin veya iptal edin.',
        ],
        'manage' => [
            'label' => 'Personeli yönet',
            'description' => 'Personel üyelerinin rollerini değiştirin ve bunları devre dışı bırakın veya yeniden etkinleştirin.',
        ],
    ],
    'catalog' => [
        'view' => [
            'label' => 'Kataloğu görüntüle',
            'description' => 'Kataloğa bakın: taslaklar, arşivlenmiş öğeler ve çöp kutusu da dahil olmak üzere markalar, kategoriler ve ürünler.',
        ],
        'manage' => [
            'label' => 'Kataloğu yönet',
            'description' => 'Markaları, kategorileri ve ürünleri oluşturup değiştirin, bunları çöp kutusuna taşıyın ve geri yükleyin.',
        ],
    ],
    'inventory' => [
        'view' => [
            'label' => 'Stoğu görüntüle',
            'description' => 'Stok seviyelerini, konumlarını ve her stok değişiminin geçmişini görün.',
        ],
        'adjust' => [
            'label' => 'Stoğu düzelt',
            'description' => 'Stok alın, stok sayımlarını düzeltin ve her bir varyantın stoğunun işlenme şeklini değiştirin.',
        ],
        'costs' => [
            'label' => 'Birim maliyetleri',
            'description' => 'Her birimin mağazaya maliyetini görün ve değiştirin. Maliyetler dahilidir ve müşterilere asla gösterilmez.',
        ],
    ],
    'customers' => [
        'view' => [
            'label' => 'Müşterileri görüntüle',
            'description' => 'Devre dışı bırakılan ve çöpe atılan hesaplar da dahil olmak üzere müşterileri, profillerini, adreslerini, personel notlarını ve gruplarını görün.',
        ],
        'manage' => [
            'label' => 'Müşterileri yönet',
            'description' => 'Müşteri profillerini değiştirin, hesapları devre dışı bırakın, yeniden etkinleştirin, çöpe atın ve geri yükleyin, not ekleyin veya silin ve müşteri gruplarını yönetin.',
        ],
        'tax_exemption' => [
            'label' => 'Vergi muafiyetleri',
            'description' => 'Müşteriyi vergiden muaf tutun veya vergi kimlik numaralarını kontrol ettikten sonra bu durumu sonlandırın.',
        ],
        'privacy' => [
            'label' => 'Müşteri veri talepleri',
            'description' => 'Müşterinin isteği üzerine, müşterinin tüm kişisel verilerini indirin veya tamamen silin.',
        ],
    ],
    'promotions' => [
        'view' => [
            'label' => 'Promosyonları görüntüle',
            'description' => 'Promosyonları, kodlarını ve ne sıklıkta kullanıldığını görün ve bir sepetin ne getireceğini önizleyin.',
        ],
        'manage' => [
            'label' => 'Promosyonları yönet',
            'description' => 'Promosyonları oluşturun, değiştirin, etkinleştirin, devre dışı bırakın ve arşivleyin, kodlarını ekleyin, oluşturun veya değiştirin.',
        ],
    ],
    'shipping' => [
        'view' => [
            'label' => 'Kargoyu görüntüle',
            'description' => 'Gönderim bölgelerini, yöntemlerini ve ücretlerini görün ve bir sepetin gönderim için ne kadar ödeyeceğini önizleyin.',
        ],
        'manage' => [
            'label' => 'Kargoyu yönet',
            'description' => 'Gönderim bölgelerini oluşturun, değiştirin ve silin; gönderim yöntemlerini ve ücretlerini oluşturun, değiştirin ve arşivleyin.',
        ],
    ],
    'orders' => [
        'view' => [
            'label' => 'Siparişleri görüntüle',
            'description' => 'Siparişleri, satırlarını, ödemelerini, geçmişini ve gönderilerini görün.',
        ],
        'manage' => [
            'label' => 'Siparişleri yönet',
            'description' => 'Siparişleri iptal edin, notlar ve etiketler ekleyin ve alıcıların iletişim bilgilerini ve teslimat adreslerini düzeltin.',
        ],
        'fulfil' => [
            'label' => 'Siparişleri hazırla',
            'description' => 'Gönderiler oluşturun, takip ekleyin ve kalanları, teslim alınmaya hazır olanları veya ulaşanları işaretleyin.',
        ],
    ],
    'invoices' => [
        'view' => [
            'label' => 'Faturaları görüntüle',
            'description' => 'Alıcının bilgileriyle birlikte faturaları ve kredi notlarını görün ve PDF’lerini indirin.',
        ],
    ],
    'payments' => [
        'view' => [
            'label' => 'Ödemeleri görüntüle',
            'description' => 'Ödemeleri, geri ödemeleri ve ödeme ağ geçitlerinin nasıl bağlandığını görün.',
        ],
        'confirm' => [
            'label' => 'Ödemeleri onayla',
            'description' => 'Bir banka havalesinin ulaştığını veya teslimatta nakit paranın tahsil edildiğini doğrulayın ve ağ geçidinden ödeme hakkında tekrar bilgi isteyin.',
        ],
        'refund' => [
            'label' => 'Ödemeleri iade et',
            'description' => 'Ödemeleri ağ geçidi aracılığıyla veya elden kaydedilerek iade edin.',
        ],
        'manage_gateways' => [
            'label' => 'Ödeme sağlayıcılarını yönet',
            'description' => 'Ödeme ağ geçitlerini ve ödeme yöntemlerini bağlayın, bağlantısını kesin ve ayarlayın.',
        ],
    ],
    'pricing' => [
        'view' => [
            'label' => 'Vergi ve para birimlerini görüntüle',
            'description' => 'Vergi sınıflarını, vergi oranlarını, her bir varyantın hangi sınıfta olduğunu ve mağazanın para birimlerini ve döviz kurlarını görün.',
        ],
        'manage' => [
            'label' => 'Vergi ve para birimlerini yönet',
            'description' => 'Vergi sınıflarını, vergi oranlarını, varyantların vergi sınıflarını, mağazanın para birimlerini, döviz kurlarını ve sabit fiyatları değiştirin.',
        ],
    ],
    'settings' => [
        'view' => [
            'label' => 'Ayarları görüntüle',
            'description' => 'Mağazanın ayarlarına bakın: ad, ülke, para birimi, diller, vergi modu, birimler ve iletişim bilgileri.',
        ],
        'manage' => [
            'label' => 'Ayarları yönet',
            'description' => 'Mağazanın ayarlarını değiştirin. Personelin sahibinde kalması için iki faktörlü kimlik doğrulamanın zorunlu kılınması.',
        ],
    ],
    'roles' => [
        'view' => [
            'label' => 'Rolleri görüntüle',
            'description' => 'Mağazanın rollerini ve her birinin neye izin verdiğini görün.',
        ],
        'manage' => [
            'label' => 'Rolleri yönet',
            'description' => 'Rolleri oluşturun, yeniden adlandırın, silin ve her birinin neye izin vereceğini seçin.',
        ],
    ],
    'delivery' => [
        'view' => [
            'label' => 'Teslimatları görüntüle',
            'description' => 'Mağazanın sürücülerini ve teslimatlarını görüntüleyin.',
        ],
        'manage' => [
            'label' => 'Teslimatları yönet',
            'description' => 'Sürücülere gönderi vermek; teslimatlarını ilerletmek, yeniden denemek, geri almak veya iptal etmek.',
        ],
        'cash' => [
            'manage' => [
                'label' => 'Sürücü nakdini kaydet',
                'description' => 'Sürücülerin teslim ettiği nakdi kaydetmek; asla ellerindekinden fazla değil.',
            ],
        ],
    ],
    'drivers' => [
        'manage' => [
            'label' => 'Sürücüleri yönet',
            'description' => 'Sürücü ekleyin, değiştirin, devre dışı bırakın ve silin; PIN seçmeleri için kod verin.',
        ],
    ],
    'returns' => [
        'view' => [
            'label' => 'İadeleri görüntüle',
            'description' => 'İadeleri, fotoğraflarını ve geçmişini, ayrıca iade ayarlarını görüntüleme.',
        ],
        'manage' => [
            'label' => 'İadeleri yönet',
            'description' => 'İade başlatma, onaylama, reddetme, teslim alma, inceleme ve iptal etme ile iade ayarlarını değiştirme.',
        ],
        'exchange' => [
            'label' => 'Değişimleri onaylama',
            'description' => 'Bir iadenin değişimini onaylama: bu, değişim siparişini oluşturur ve iade edilen ürünlerin değerini hesaplar.',
        ],
    ],
    'storefront' => [
        'view' => [
            'label' => 'Vitrin içeriğini görüntüleme',
            'description' => 'Mağazanın sayfalarını, menülerini, bannerlarını ve vitrin ayarlarını görme.',
        ],
        'manage' => [
            'label' => 'Vitrin içeriğini yönetme',
            'description' => 'Mağazanın sayfalarını, menülerini ve bannerlarını ekleme, değiştirme, yayımlama ve kaldırma ve vitrin ayarlarını değiştirme.',
        ],
    ],
];
