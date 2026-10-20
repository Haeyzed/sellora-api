<?php

declare(strict_types=1);

return [
    'not_translations' => ':attribute 必须是由语言键入的文本对象，例如 {"en": "..."}。',
    'locale_not_enabled' => '该商店未在“:locale”中发布。首先在商店设置中启用语言。',
    'default_required' => ':attribute 需要商店默认语言 (:locale) 的文本。',
    'empty' => '“:locale”中的:attribute不能为空。发送 null 以删除该翻译。',
    'too_long' => '“:locale”中的 :attribute 不得长于 :max 个字符。',
];
