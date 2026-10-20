<?php

declare(strict_types=1);

return [
    'not_translations' => ':attribute должен быть объектом текста с указанием языка, например {"en": "…"}.',
    'locale_not_enabled' => 'Магазин не публикует информацию в «:locale». Сначала включите язык в настройках магазина.',
    'default_required' => ':attribute нужен текст на языке магазина по умолчанию (:locale).',
    'empty' => ':attribute в «:locale» не может быть пустым. Отправьте значение null, чтобы удалить этот перевод.',
    'too_long' => 'Длина :attribute в «:locale» не может превышать :max символов.',
];
