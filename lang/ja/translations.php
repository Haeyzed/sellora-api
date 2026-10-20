<?php

declare(strict_types=1);

return [
    'not_translations' => ':attribute は、{"en": "…"} など、言語によってキー付けされたテキストのオブジェクトである必要があります。',
    'locale_not_enabled' => 'ストアでは「:locale」には掲載しておりません。まずストア設定で言語を有効にしてください。',
    'default_required' => ':attribute には、ストアのデフォルト言語 (:locale) のテキストが必要です。',
    'empty' => '「:locale」の :attribute を空にすることはできません。その翻訳を削除するには、null を送信します。',
    'too_long' => '「:locale」の :attribute は :max 文字を超えてはなりません。',
];
