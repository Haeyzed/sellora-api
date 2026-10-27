<?php

declare(strict_types=1);

return [
    'offer' => [
        'subject' => ':owner seni mağazasının sahibi yapmak istiyor',
        'reason' => ':owner sizden mağazanın sahibi olarak görevi devralmanızı istedi. Sahibi olarak mağazadaki her şeyi yapabilirsiniz ve mağazanın :app ile olan sözleşmesi sizin olur.',
        'terms' => 'Kabul etmek için mağaza kontrol panelinde oturum açın ve :app\'nın mevcut hizmet şartlarını kabul edin. Sen değiştirene kadar hiçbir şey değişmez.',
        'action' => 'Aktarımı inceleyin',
        'expiry' => 'Bu teklifin süresi :hours saat içinde doluyor.',
        'ignore' => 'Mağazayı devralmak istemiyorsanız bu e-postayı dikkate almayın.',
    ],
    'completed' => [
        'subject' => ':store mağazasının yeni bir sahibi var',
        'new_owner' => 'Artık :store mağazasının sahibisiniz.',
        'previous_owner' => ':owner artık :store mağazasının sahibidir. Yalnızca aktarımı başlattığınızda seçtiğiniz roller sizde kalır.',
        'not_you' => 'Bunu beklemiyorsanız hemen :app desteğiyle iletişime geçin.',
    ],
];
