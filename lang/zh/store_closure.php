<?php

declare(strict_types=1);

return [
    'closed' => [
        'subject' => '您的商店 :store 已关闭',
        'line' => '您的商店 :store 已关闭。它不再对任何人开放，每个人都已退出。其数据将在 :date 上永久删除。',
    ],
    'reminder' => [
        'subject' => '您的商店 :store 将于 :date 被删除',
        'line' => '您的商店 :store 已关闭。其数据将在 :date 上永久删除，并且此后无法恢复。',
    ],
    'export' => '如果您需要商店数据的副本，或者想要重新打开商店，请在 :date 之前联系 :app 支持：他们可以恢复商店，以便您可以导出所有内容。',
    'not_you' => '如果您没有关闭这家商店，请立即联系 :app 支持人员。',
];
