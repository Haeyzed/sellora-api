<?php

declare(strict_types=1);

return [
    'code' => [
        'subject' => ':code はストア確認コードです',
        'reason' => 'このコードを使用して、:store の登録を完了します。',
        'code' => 'コード: :code',
        'expiry' => ':minutes 分で期限切れになります。',
        'ignore' => 'ストアを登録しようとしていない場合は、このメールを無視してください。',
    ],
    'ready' => [
        'subject' => ':store の準備ができました',
        'line' => 'ストア :store がセットアップされました。サインインして製品を追加し、販売を開始します。',
        'action' => 'ダッシュボードに移動します',
    ],
];
