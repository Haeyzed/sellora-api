<?php

declare(strict_types=1);

return [
    'closed' => [
        'subject' => 'あなたのストア :store は閉店しました',
        'line' => 'Your store :store is closed.もう誰にも開かれず、全員がサインアウトされました。 Its data will be deleted for good on :date.',
    ],
    'reminder' => [
        'subject' => 'ストア :store は :date に削除されます',
        'line' => 'あなたのストア :store は閉店しました。そのデータは :date で完全に削除され、その後復元することはできません。',
    ],
    'export' => 'ストアのデータのコピーが必要な場合、またはストアを再開したい場合は、:date より前に :app サポートにご連絡ください。ストアを復元できるので、すべてをエクスポートできます。',
    'not_you' => 'このストアを閉鎖していない場合は、すぐに :app サポートにご連絡ください。',
];
